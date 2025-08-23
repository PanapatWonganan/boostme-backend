<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SystemDebugController extends Controller
{
    public function checkSystem()
    {
        $checks = [];
        
        // Check PHP configuration
        $checks['php_config'] = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
        ];
        
        // Check storage paths
        $storagePath = storage_path('app/private');
        $tempVideosPath = storage_path('app/private/temp-videos');
        $videosPath = storage_path('app/private/videos');
        
        $checks['storage'] = [
            'storage_path_exists' => file_exists($storagePath),
            'storage_path_writable' => is_writable($storagePath),
            'temp_videos_exists' => file_exists($tempVideosPath),
            'temp_videos_writable' => is_writable($tempVideosPath),
            'videos_path_exists' => file_exists($videosPath),
            'videos_path_writable' => is_writable($videosPath),
            'storage_path' => $storagePath,
            'disk_free_space' => disk_free_space($storagePath) / (1024 * 1024 * 1024) . ' GB',
        ];
        
        // Check database connection
        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'connected' => true,
                'driver' => DB::connection()->getDriverName(),
                'videos_table_exists' => DB::table('information_schema.tables')
                    ->where('table_schema', DB::getDatabaseName())
                    ->where('table_name', 'videos')
                    ->exists(),
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
        
        // Check FFmpeg availability
        $ffmpegPath = shell_exec('which ffmpeg 2>/dev/null');
        $checks['ffmpeg'] = [
            'available' => !empty($ffmpegPath),
            'path' => $ffmpegPath ?: 'not found',
        ];
        
        // List files in temp-videos directory
        try {
            $tempFiles = Storage::disk('local')->files('temp-videos');
            $checks['temp_files'] = [
                'count' => count($tempFiles),
                'files' => array_slice($tempFiles, 0, 10), // Show first 10 files
            ];
        } catch (\Exception $e) {
            $checks['temp_files'] = [
                'error' => $e->getMessage(),
            ];
        }
        
        // Check environment
        $checks['environment'] = [
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'filesystem_disk' => config('filesystems.default'),
            'queue_connection' => config('queue.default'),
        ];
        
        // Check recent video uploads
        try {
            $recentVideos = \App\Models\Video::latest()
                ->take(5)
                ->get(['id', 'title', 'status', 'created_at', 'processing_error']);
            $checks['recent_videos'] = $recentVideos;
        } catch (\Exception $e) {
            $checks['recent_videos'] = [
                'error' => $e->getMessage(),
            ];
        }
        
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ]);
    }
    
    public function testVideoStream(Request $request, $videoId)
    {
        try {
            $video = \App\Models\Video::findOrFail($videoId);
            
            $info = [
                'video_id' => $video->id,
                'title' => $video->title,
                'status' => $video->status,
                'ready' => $video->isReady(),
                'original_path' => $video->original_path,
                'hls_path' => $video->hls_path,
                'mime_type' => $video->mime_type,
                'file_size' => $video->file_size,
            ];
            
            // Check if files exist
            $possiblePaths = [
                storage_path('app/private/' . $video->original_path),
                storage_path('app/' . $video->original_path),
                Storage::disk('local')->path($video->original_path),
                '/app/storage/app/private/' . $video->original_path,
                '/app/storage/app/' . $video->original_path,
                storage_path('app/private/' . $video->hls_path),
                Storage::disk('local')->path($video->hls_path ?? '')
            ];
            
            $pathChecks = [];
            $foundPath = null;
            
            foreach ($possiblePaths as $path) {
                $exists = file_exists($path);
                $pathChecks[] = [
                    'path' => $path,
                    'exists' => $exists,
                    'size' => $exists ? filesize($path) : 0,
                ];
                
                if ($exists && !$foundPath) {
                    $foundPath = $path;
                }
            }
            
            $info['path_checks'] = $pathChecks;
            $info['found_path'] = $foundPath;
            
            if ($foundPath) {
                // Try to detect MIME type
                $extension = strtolower(pathinfo($foundPath, PATHINFO_EXTENSION));
                $info['file_extension'] = $extension;
                
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $detectedMime = finfo_file($finfo, $foundPath);
                        finfo_close($finfo);
                        $info['detected_mime'] = $detectedMime;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'video_info' => $info,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function downloadVideo(Request $request, $videoId)
    {
        try {
            $video = \App\Models\Video::findOrFail($videoId);
            
            // Find the video file
            $possiblePaths = [
                storage_path('app/private/' . $video->original_path),
                storage_path('app/' . $video->original_path),
                Storage::disk('local')->path($video->original_path),
                '/app/storage/app/private/' . $video->original_path,
                '/app/storage/app/' . $video->original_path,
                storage_path('app/private/' . $video->hls_path),
                Storage::disk('local')->path($video->hls_path ?? '')
            ];
            
            $path = null;
            foreach ($possiblePaths as $testPath) {
                if (file_exists($testPath)) {
                    $path = $testPath;
                    break;
                }
            }
            
            if (!$path || !file_exists($path)) {
                return response()->json([
                    'error' => 'Video file not found',
                    'checked_paths' => $possiblePaths
                ], 404);
            }
            
            // Stream the file directly without token validation (debug only)
            return response()->stream(
                function () use ($path) {
                    $stream = fopen($path, 'rb');
                    while (!feof($stream)) {
                        echo fread($stream, 8192);
                        flush();
                    }
                    fclose($stream);
                },
                200,
                [
                    'Content-Type' => 'video/mp4',
                    'Content-Length' => filesize($path),
                    'Content-Disposition' => 'attachment; filename="debug_video.mp4"',
                    'Access-Control-Allow-Origin' => '*',
                ]
            );
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function testUpload(Request $request)
    {
        // Simple test upload without processing
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max for test
        ]);
        
        try {
            $file = $request->file('file');
            $path = $file->store('test-uploads', 'local');
            
            // Check if file exists
            $fullPath = Storage::disk('local')->path($path);
            $exists = file_exists($fullPath);
            
            return response()->json([
                'success' => true,
                'stored_path' => $path,
                'full_path' => $fullPath,
                'file_exists' => $exists,
                'file_size' => $exists ? filesize($fullPath) : 0,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
    
    public function cleanupOldVideos()
    {
        try {
            // Find videos with localhost paths
            $oldVideos = \App\Models\Video::where('original_path', 'like', '%/Users/panapat/%')
                ->orWhere('original_path', 'like', '%localhost%')
                ->orWhere('original_path', 'like', '%fitness-lms-admin%')
                ->get();
                
            $deletedCount = $oldVideos->count();
            
            foreach ($oldVideos as $video) {
                // Also delete related records if any
                $video->accessLogs()->delete();
                $video->delete();
            }
            
            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Deleted {$deletedCount} old video records with localhost paths"
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function listVideos()
    {
        try {
            $videos = \App\Models\Video::latest()->take(10)->get([
                'id', 'title', 'status', 'original_path', 'hls_path', 'created_at'
            ]);
            
            return response()->json([
                'success' => true,
                'videos' => $videos,
                'total_videos' => \App\Models\Video::count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reprocessVideos(Request $request)
    {
        try {
            $videosToReprocess = \App\Models\Video::whereIn('status', ['pending', 'failed'])
                ->orWhere(function($query) {
                    $query->where('status', 'processing')
                          ->where('created_at', '<', now()->subMinutes(10)); // Stuck for more than 10 minutes
                })
                ->get();

            $processedCount = 0;
            
            foreach ($videosToReprocess as $video) {
                $video->update([
                    'status' => 'pending',
                    'processing_error' => null
                ]);
                
                \App\Jobs\ProcessVideoJob::dispatch($video);
                $processedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Videos queued for reprocessing',
                'reprocessed_count' => $processedCount,
                'videos' => $videosToReprocess->map(function($video) {
                    return [
                        'id' => $video->id,
                        'title' => $video->title,
                        'previous_status' => $video->status,
                        'created_at' => $video->created_at->diffForHumans()
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkVideoFile($videoId)
    {
        try {
            $video = \App\Models\Video::findOrFail($videoId);
            
            // Check all possible paths
            $paths = [
                storage_path('app/private/' . $video->original_path),
                storage_path('app/' . $video->original_path),
                \Illuminate\Support\Facades\Storage::disk('local')->path($video->original_path),
                '/app/storage/app/private/' . $video->original_path,
                '/app/storage/app/' . $video->original_path,
            ];
            
            $pathResults = [];
            $foundPath = null;
            
            foreach ($paths as $path) {
                $exists = file_exists($path);
                $pathResults[] = [
                    'path' => $path,
                    'exists' => $exists,
                    'size' => $exists ? filesize($path) : null,
                    'readable' => $exists ? is_readable($path) : null
                ];
                
                if ($exists && !$foundPath) {
                    $foundPath = $path;
                }
            }
            
            // List actual files in temp-videos directory
            $tempVideosPath = '/app/storage/app/private/temp-videos';
            $actualFiles = [];
            if (is_dir($tempVideosPath)) {
                $files = scandir($tempVideosPath);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $actualFiles[] = [
                            'name' => $file,
                            'size' => filesize($tempVideosPath . '/' . $file),
                            'modified' => date('Y-m-d H:i:s', filemtime($tempVideosPath . '/' . $file))
                        ];
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'video_info' => [
                    'id' => $video->id,
                    'title' => $video->title,
                    'original_path' => $video->original_path,
                    'hls_path' => $video->hls_path,
                    'status' => $video->status,
                    'processing_error' => $video->processing_error
                ],
                'path_checks' => $pathResults,
                'found_file' => $foundPath,
                'temp_videos_directory' => [
                    'path' => $tempVideosPath,
                    'exists' => is_dir($tempVideosPath),
                    'files' => $actualFiles
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}