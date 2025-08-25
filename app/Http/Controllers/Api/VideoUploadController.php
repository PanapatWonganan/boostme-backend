<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Lesson;
use App\Models\Video;
use App\Models\VideoAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class VideoUploadController extends Controller
{
    /**
     * Upload a new video
     */
    public function upload(Request $request)
    {
        $request->validate([
            'video' => [
                'required',
                File::types(['mp4', 'mov', 'avi', 'webm'])
                    ->max(500 * 1024), // 500MB max for Railway (reduced from 2GB)
            ],
            'lesson_id' => 'nullable|exists:lessons,id',
            'title' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Store the uploaded file temporarily
            $file = $request->file('video');
            $tempPath = $file->store('temp-videos', 'local');
            
            // Create video record
            $video = Video::create([
                'title' => $request->title,
                'lesson_id' => $request->lesson_id,
                'original_filename' => $file->getClientOriginalName(),
                'original_path' => $tempPath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending',
                'metadata' => [
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now()->toISOString(),
                    'original_extension' => $file->getClientOriginalExtension(),
                ]
            ]);
            
            // Process video immediately instead of queuing (for Railway deployment)
            // ProcessVideoJob::dispatch($video);
            
            // Direct processing for Railway without queue
            try {
                $job = new \App\Jobs\ProcessVideoJob($video);
                $job->handle();
            } catch (\Exception $e) {
                \Log::error('Direct video processing failed: ' . $e->getMessage());
                // Continue anyway - video is uploaded
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Video uploaded successfully and queued for processing',
                'video' => [
                    'id' => $video->id,
                    'title' => $video->title,
                    'status' => $video->status,
                    'size' => $video->formatted_size,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            
            // Clean up uploaded file if exists
            if (isset($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }
            
            return response()->json([
                'message' => 'Failed to upload video',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get video status
     */
    public function status($videoId)
    {
        $video = Video::findOrFail($videoId);
        
        return response()->json([
            'id' => $video->id,
            'title' => $video->title,
            'status' => $video->status,
            'duration' => $video->formatted_duration,
            'size' => $video->formatted_size,
            'processing_error' => $video->processing_error,
            'created_at' => $video->created_at,
            'updated_at' => $video->updated_at,
        ]);
    }
    
    /**
     * Generate secure streaming URL
     */
    public function generateStreamUrl(Request $request, $videoId)
    {
        $video = Video::findOrFail($videoId);
        
        // Check if video is ready
        if (!$video->isReady()) {
            return response()->json([
                'message' => 'Video is not ready for streaming',
                'status' => $video->status
            ], 400);
        }
        
        // Check user access permissions
        if ($video->lesson_id) {
            $lesson = Lesson::find($video->lesson_id);
            if ($lesson && !$lesson->is_free) {
                // TODO: Check if user has purchased the course
                // For now, we'll allow all authenticated users
                if (!auth()->check()) {
                    return response()->json([
                        'message' => 'Authentication required'
                    ], 401);
                }
            }
        }
        
        // Generate signed URL with short expiration for security
        $expires = now()->addMinutes(30); // Shorter expiration for LMS security
        $userId = auth()->id() ?? 'anonymous';
        
        $token = hash_hmac(
            'sha256',
            "{$videoId}:{$userId}:{$expires->timestamp}",
            config('app.key')
        );
        
        // Log access attempt
        if (auth()->check()) {
            VideoAccessLog::create([
                'user_id' => auth()->id(),
                'video_id' => $video->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_fingerprint' => $request->header('X-Device-Fingerprint'),
                'started_at' => now(),
            ]);
        }
        
        // Build secure streaming URL
        $streamUrl = route('api.video.stream', [
            'video' => $videoId,
            'user' => $userId,
            'expires' => $expires->timestamp,
            'token' => $token
        ]);
        
        return response()->json([
            'url' => $streamUrl,
            'expires_at' => $expires->toISOString(),
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'duration' => $video->formatted_duration,
            ]
        ]);
    }
    
    /**
     * Stream video content (HLS)
     */
    public function stream(Request $request, $videoId)
    {
        \Log::info('Video stream request:', [
            'video_id' => $videoId,
            'user' => $request->query('user'),
            'expires' => $request->query('expires'),
            'token_provided' => $request->query('token') ? 'yes' : 'no',
            'current_time' => now()->timestamp,
            'ip' => $request->ip()
        ]);
        
        // Validate token
        $userId = $request->query('user');
        $expires = $request->query('expires');
        $token = $request->query('token');
        
        if (!$userId || !$expires || !$token) {
            \Log::warning('Missing required parameters for video stream');
            return response()->json(['message' => 'Missing required parameters'], 400);
        }
        
        $expectedToken = hash_hmac(
            'sha256',
            "{$videoId}:{$userId}:{$expires}",
            config('app.key')
        );
        
        \Log::debug('Token validation:', [
            'expected' => substr($expectedToken, 0, 10) . '...',
            'received' => substr($token, 0, 10) . '...',
            'match' => hash_equals($expectedToken, $token)
        ]);
        
        if (!hash_equals($expectedToken, $token)) {
            \Log::warning('Invalid token for video stream');
            return response()->json(['message' => 'Invalid token'], 403);
        }
        
        if (now()->timestamp > $expires) {
            \Log::warning('Expired token for video stream');
            return response()->json(['message' => 'Token expired'], 403);
        }
        
        $video = Video::findOrFail($videoId);
        
        if (!$video->isReady()) {
            return response()->json(['message' => 'Video not available'], 404);
        }
        
        // Serve the processed video file
        $path = null;
        
        // Priority order: processed_path first, then hls_path (if HLS), then original_path
        $pathsToTry = [];
        
        // Check metadata to determine if this is HLS or direct MP4
        $metadata = $video->metadata ?? [];
        $isHLS = ($metadata['is_hls'] ?? false) || str_contains($video->hls_path ?? '', '.m3u8');
        
        if ($isHLS && $video->hls_path) {
            // This is an HLS stream
            $pathsToTry[] = $video->hls_path;
        } elseif (isset($metadata['copied_file_path'])) {
            // This is a processed MP4 file (new format)
            $pathsToTry[] = $metadata['copied_file_path'];
        } elseif ($video->processed_path ?? false) {
            // Fallback to processed_path field
            $pathsToTry[] = $video->processed_path;
        }
        
        // Always try original_path as final fallback
        if ($video->original_path) {
            $pathsToTry[] = $video->original_path;
        }
        
        foreach ($pathsToTry as $videoPath) {
            $possiblePaths = [
                // Railway production paths
                '/app/storage/app/' . ltrim($videoPath, '/'),
                '/app/storage/app/private/' . ltrim($videoPath, '/'),
                // Local development paths  
                storage_path('app/' . ltrim($videoPath, '/')),
                storage_path('app/private/' . ltrim($videoPath, '/')),
                // Laravel Storage facade paths
                Storage::disk('local')->path($videoPath),
                // Absolute path if provided
                $videoPath
            ];
            
            foreach ($possiblePaths as $testPath) {
                if (file_exists($testPath)) {
                    $path = $testPath;
                    \Log::info('Video file found:', ['path' => $testPath]);
                    break 2; // Break both loops
                }
            }
        }
        
        if (!$path || !file_exists($path)) {
            \Log::error('Video file not found:', [
                'video_id' => $videoId,
                'paths_tried' => $pathsToTry,
                'video_original_path' => $video->original_path,
                'video_hls_path' => $video->hls_path,
                'video_status' => $video->status,
                'video_created_at' => $video->created_at,
                'all_possible_paths' => $possiblePaths ?? []
            ]);
            
            // Return 404 with debug info for development
            return response()->json([
                'message' => 'Video file not found on server storage',
                'debug' => [
                    'video_id' => $videoId,
                    'status' => $video->status,
                    'original_path' => $video->original_path,
                    'hls_path' => $video->hls_path,
                    'suggestion' => 'Please re-upload the video file through admin panel'
                ]
            ], 404);
        }
        
        $fileSize = filesize($path);
        $mimeType = $this->detectVideoMimeType($path, $video->mime_type);
        
        \Log::info('Video file found:', [
            'video_id' => $videoId,
            'file_path' => $path,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'video_mime_type' => $video->mime_type
        ]);
        $length = $fileSize;
        $start = 0;
        $end = $fileSize - 1;
        
        // Handle range requests for video streaming
        if ($request->hasHeader('Range')) {
            $range = $request->header('Range');
            list($param, $range) = explode('=', $range);
            
            if (strtolower(trim($param)) !== 'bytes') {
                return response('Not Satisfiable', 416)
                    ->header('Content-Range', "bytes */$fileSize");
            }
            
            $range = explode(',', $range)[0];
            list($from, $to) = explode('-', $range);
            
            if ($from === '') {
                $end = $fileSize - 1;
                $start = $end - intval($from);
            } elseif ($to === '') {
                $start = intval($from);
            } else {
                $start = intval($from);
                $end = intval($to);
            }
            
            if ($start >= $fileSize || $end >= $fileSize) {
                return response('Not Satisfiable', 416)
                    ->header('Content-Range', "bytes */$fileSize");
            }
            
            $length = $end - $start + 1;
            
            return response()->stream(
                function () use ($path, $start, $length) {
                    $stream = fopen($path, 'rb');
                    fseek($stream, $start);
                    echo fread($stream, $length);
                    fclose($stream);
                },
                206,
                [
                    'Content-Type' => $mimeType,
                    'Content-Length' => $length,
                    'Accept-Ranges' => 'bytes',
                    'Content-Range' => "bytes $start-$end/$fileSize",
                    'Access-Control-Allow-Origin' => config('app.frontend_url', '*'),
                    'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Range, Content-Type, Authorization',
                    'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ]
            );
        }
        
        return response()->stream(
            function () use ($path) {
                $stream = fopen($path, 'rb');
                $chunkSize = 8192; // 8KB chunks
                
                while (!feof($stream)) {
                    echo fread($stream, $chunkSize);
                    flush();
                    
                    // Check connection status to prevent overwhelming
                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }
                
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Access-Control-Allow-Origin' => config('app.frontend_url', '*'),
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => 'Range, Content-Type, Authorization',
                'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                // Add specific headers for video playback
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',  // Disable nginx buffering for streaming
            ]
        );
    }
    
    /**
     * Update video access log (for tracking watch time, seeks, etc.)
     */
    public function updateAccessLog(Request $request, $logId)
    {
        $request->validate([
            'watch_duration' => 'integer|min:0',
            'seek_count' => 'integer|min:0',
            'speed_changes' => 'integer|min:0',
            'ended' => 'boolean',
        ]);
        
        $log = VideoAccessLog::where('id', $logId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        $log->update([
            'watch_duration' => $request->watch_duration ?? $log->watch_duration,
            'seek_count' => $request->seek_count ?? $log->seek_count,
            'speed_changes' => $request->speed_changes ?? $log->speed_changes,
            'ended_at' => $request->ended ? now() : null,
        ]);
        
        // Check for suspicious activity
        if ($log->seek_count > 50) {
            $log->markSuspicious('Excessive seeking detected');
        }
        
        if ($log->speed_changes > 20) {
            $log->markSuspicious('Excessive speed changes detected');
        }
        
        return response()->json([
            'message' => 'Access log updated',
            'log_id' => $log->id
        ]);
    }
    
    /**
     * Detect proper MIME type for video file
     */
    private function detectVideoMimeType(string $filePath, ?string $storedMimeType): string
    {
        // Try to detect MIME type from file extension first (most reliable)
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeMap = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime', 
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            'm4v' => 'video/mp4',  // Treat m4v as mp4 for better browser support
            '3gp' => 'video/3gpp',
            'flv' => 'video/x-flv',
            'wmv' => 'video/x-ms-wmv',
            'mkv' => 'video/x-matroska'
        ];
        
        \Log::info('MIME type detection:', [
            'file_path' => $filePath,
            'extension' => $extension,
            'stored_mime' => $storedMimeType
        ]);
        
        // Use extension-based MIME type if available (most reliable)
        if (isset($mimeMap[$extension])) {
            \Log::info('Using extension-based MIME type:', ['mime_type' => $mimeMap[$extension]]);
            return $mimeMap[$extension];
        }
        
        // Try using PHP's finfo if available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                if ($detectedMime && str_starts_with($detectedMime, 'video/')) {
                    \Log::info('Using finfo detected MIME type:', ['mime_type' => $detectedMime]);
                    return $detectedMime;
                }
            }
        }
        
        // Fallback - prefer video/mp4 as it has best browser support
        $fallbackMime = 'video/mp4';
        if ($storedMimeType && str_starts_with($storedMimeType, 'video/')) {
            $fallbackMime = $storedMimeType;
        }
        
        \Log::info('Using fallback MIME type:', ['mime_type' => $fallbackMime]);
        return $fallbackMime;
    }
}