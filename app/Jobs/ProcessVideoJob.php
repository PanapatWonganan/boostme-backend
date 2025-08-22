<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $video;
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;
    
    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting video processing for video ID: {$this->video->id}");
            
            // Update status to processing
            $this->video->update(['status' => 'processing']);
            
            // Get the video file path - build full path manually
            $originalPath = $this->video->original_path;
            
            // Try different path combinations to find the file
            $possiblePaths = [
                storage_path('app/private/' . $originalPath),
                storage_path('app/' . $originalPath),
                Storage::disk('local')->path('private/' . $originalPath),
                Storage::disk('local')->path($originalPath)
            ];
            
            $inputPath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $inputPath = $path;
                    break;
                }
            }
            
            if (!$inputPath) {
                $pathsList = implode(', ', $possiblePaths);
                throw new \Exception("Video file not found in any of these paths: {$pathsList}");
            }
            
            if (!file_exists($inputPath)) {
                throw new \Exception("Video file not found: {$inputPath}");
            }
            
            // Check if FFmpeg is available
            $ffmpegAvailable = $this->checkFFmpegAvailable();
            
            if ($ffmpegAvailable) {
                // Full processing with FFmpeg
                $this->processWithFFmpeg($inputPath);
            } else {
                // Basic processing without FFmpeg
                $this->processWithoutFFmpeg($inputPath);
            }
            
            Log::info("Video processing completed for video ID: {$this->video->id}");
            
        } catch (\Exception $e) {
            Log::error("Video processing failed for video ID: {$this->video->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->video->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    private function checkFFmpegAvailable(): bool
    {
        $result = shell_exec('which ffmpeg 2>/dev/null');
        return !empty($result);
    }
    
    private function processWithoutFFmpeg(string $inputPath): void
    {
        Log::info("Processing video without FFmpeg for video ID: {$this->video->id}");
        
        // Get basic file information
        $fileSize = filesize($inputPath);
        
        // Create a simple video record without conversion
        // For demo purposes, we'll create a direct link to the original file
        $outputDir = 'videos/' . $this->video->id;
        Storage::disk('local')->makeDirectory($outputDir);
        
        // Copy original file to video directory
        $originalFileName = basename($this->video->original_path);
        $newPath = $outputDir . '/' . $originalFileName;
        Storage::disk('local')->copy($this->video->original_path, $newPath);
        
        // Update video record
        $this->video->update([
            'status' => 'ready',
            'hls_path' => $newPath, // Use direct path instead of HLS
            'duration_seconds' => 0, // We can't determine duration without FFmpeg
            'metadata' => array_merge($this->video->metadata ?? [], [
                'processed_at' => now()->toISOString(),
                'processing_method' => 'direct_copy',
                'ffmpeg_available' => false
            ])
        ]);
    }
    
    private function processWithFFmpeg(string $inputPath): void
    {
        Log::info("Processing video with FFmpeg for video ID: {$this->video->id}");
        
        // Get video information using ffprobe
        $duration = $this->getVideoDuration($inputPath);
        $this->video->update(['duration_seconds' => $duration]);
        
        // Create output directory for HLS segments
        $outputDir = 'videos/' . $this->video->id;
        Storage::disk('local')->makeDirectory($outputDir);
        $outputPath = Storage::disk('local')->path($outputDir);
        
        // Generate encryption key for HLS
        $encryptionKey = Str::random(32);
        $keyFile = $outputPath . '/encryption.key';
        file_put_contents($keyFile, $encryptionKey);
        
        // Create key info file for ffmpeg
        $keyInfoFile = $outputPath . '/keyinfo.txt';
        $keyUrl = url('/api/video/key/' . $this->video->id);
        file_put_contents($keyInfoFile, 
            $keyUrl . PHP_EOL . 
            $keyFile . PHP_EOL . 
            bin2hex(random_bytes(16))
        );
        
        // Convert video to HLS format with encryption
        $this->convertToHLS($inputPath, $outputPath, $keyInfoFile);
        
        // Update video record with processed information
        $this->video->update([
            'status' => 'ready',
            'hls_path' => $outputDir . '/index.m3u8',
            'encryption_key' => encrypt($encryptionKey),
            'metadata' => array_merge($this->video->metadata ?? [], [
                'processed_at' => now()->toISOString(),
                'segments_count' => count(glob($outputPath . '/*.ts')),
                'processing_method' => 'hls_conversion',
                'ffmpeg_available' => true
            ])
        ]);
    }
    
    /**
     * Get video duration using ffprobe
     */
    private function getVideoDuration(string $inputPath): int
    {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($inputPath);
        $output = shell_exec($cmd);
        
        if (!$output) {
            // Try alternative method
            $cmd = "ffmpeg -i " . escapeshellarg($inputPath) . " 2>&1 | grep Duration | awk '{print $2}' | tr -d ,";
            $output = shell_exec($cmd);
            
            if ($output) {
                // Parse time format HH:MM:SS.ms
                $parts = explode(':', $output);
                if (count($parts) == 3) {
                    $hours = intval($parts[0]);
                    $minutes = intval($parts[1]);
                    $seconds = intval($parts[2]);
                    return ($hours * 3600) + ($minutes * 60) + $seconds;
                }
            }
            
            return 0;
        }
        
        return intval($output);
    }
    
    /**
     * Convert video to HLS format with encryption
     */
    private function convertToHLS(string $inputPath, string $outputPath, string $keyInfoFile): void
    {
        // Basic HLS conversion command
        $cmd = "ffmpeg -i " . escapeshellarg($inputPath) . " " .
               "-profile:v baseline " .
               "-level 3.0 " .
               "-start_number 0 " .
               "-hls_time 10 " .
               "-hls_list_size 0 " .
               "-hls_key_info_file " . escapeshellarg($keyInfoFile) . " " .
               "-hls_segment_filename " . escapeshellarg($outputPath . '/segment_%03d.ts') . " " .
               "-f hls " .
               escapeshellarg($outputPath . '/index.m3u8') . " 2>&1";
        
        Log::info("Executing FFmpeg command: " . $cmd);
        
        $output = shell_exec($cmd);
        
        if (!file_exists($outputPath . '/index.m3u8')) {
            // If HLS conversion failed, try simpler conversion without encryption for now
            $cmd = "ffmpeg -i " . escapeshellarg($inputPath) . " " .
                   "-codec: copy " .
                   "-start_number 0 " .
                   "-hls_time 10 " .
                   "-hls_list_size 0 " .
                   "-hls_segment_filename " . escapeshellarg($outputPath . '/segment_%03d.ts') . " " .
                   "-f hls " .
                   escapeshellarg($outputPath . '/index.m3u8') . " 2>&1";
            
            $output = shell_exec($cmd);
            
            if (!file_exists($outputPath . '/index.m3u8')) {
                throw new \Exception("Failed to convert video to HLS format. FFmpeg output: " . $output);
            }
        }
        
        Log::info("HLS conversion completed");
    }
    
    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessVideoJob failed permanently for video ID: {$this->video->id}", [
            'error' => $exception->getMessage()
        ]);
        
        $this->video->update([
            'status' => 'failed',
            'processing_error' => 'Processing failed after multiple attempts: ' . $exception->getMessage()
        ]);
    }
}