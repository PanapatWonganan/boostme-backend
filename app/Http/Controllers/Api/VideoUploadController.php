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
                    ->max(2 * 1024 * 1024), // 2GB max
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
        // Validate token
        $userId = $request->query('user');
        $expires = $request->query('expires');
        $token = $request->query('token');
        
        $expectedToken = hash_hmac(
            'sha256',
            "{$videoId}:{$userId}:{$expires}",
            config('app.key')
        );
        
        if (!hash_equals($expectedToken, $token)) {
            return response()->json(['message' => 'Invalid token'], 403);
        }
        
        if (now()->timestamp > $expires) {
            return response()->json(['message' => 'Token expired'], 403);
        }
        
        $video = Video::findOrFail($videoId);
        
        if (!$video->isReady()) {
            return response()->json(['message' => 'Video not available'], 404);
        }
        
        // Serve the processed video file
        // If HLS path exists, use it; otherwise fallback to processed video
        if ($video->hls_path && !str_ends_with($video->hls_path, '.m3u8')) {
            // Direct video file (not HLS)
            $path = Storage::disk('local')->path($video->hls_path);
        } else {
            // Try to find the original file with correct path handling
            $originalPath = $video->original_path;
            $possiblePaths = [
                storage_path('app/private/' . $originalPath),
                storage_path('app/' . $originalPath),
                Storage::disk('local')->path($originalPath),
                '/app/storage/app/private/' . $originalPath,  // Railway absolute path
                '/app/storage/app/' . $originalPath,  // Railway alternate path
                storage_path('app/private/' . $video->hls_path), // Try hls_path too
                Storage::disk('local')->path($video->hls_path ?? '')
            ];
            
            $path = null;
            foreach ($possiblePaths as $testPath) {
                if (file_exists($testPath)) {
                    $path = $testPath;
                    break;
                }
            }
        }
        
        if (!$path || !file_exists($path)) {
            return response()->json(['message' => 'Video file not found'], 404);
        }
        
        $fileSize = filesize($path);
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
                    'Content-Type' => $video->mime_type ?? 'video/mp4',
                    'Content-Length' => $length,
                    'Accept-Ranges' => 'bytes',
                    'Content-Range' => "bytes $start-$end/$fileSize",
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Range, Content-Type',
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
                    
                    // Small delay to prevent overwhelming the connection
                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }
                
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $video->mime_type ?? 'video/mp4',
                'Content-Length' => $fileSize,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'no-cache, no-store, private, must-revalidate',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Headers' => 'Range',
                'Content-Disposition' => 'inline; filename="secure_video.mp4"',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
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
}