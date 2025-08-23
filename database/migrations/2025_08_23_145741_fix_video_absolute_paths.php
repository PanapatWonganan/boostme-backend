<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all videos with absolute paths
        $videos = DB::table('videos')
            ->where(function ($query) {
                $query->where('original_path', 'like', '/%')
                    ->orWhere('original_path', 'like', 'C:%')
                    ->orWhere('original_path', 'like', '/Users/%')
                    ->orWhere('hls_path', 'like', '/%')
                    ->orWhere('hls_path', 'like', 'C:%')
                    ->orWhere('hls_path', 'like', '/Users/%');
            })
            ->get();

        foreach ($videos as $video) {
            $updates = [];
            
            // Fix original_path
            if ($video->original_path) {
                // Extract relative path from absolute path
                if (str_contains($video->original_path, 'temp-videos/')) {
                    $parts = explode('temp-videos/', $video->original_path);
                    $updates['original_path'] = 'temp-videos/' . end($parts);
                } elseif (str_contains($video->original_path, 'videos/')) {
                    $parts = explode('videos/', $video->original_path);
                    $updates['original_path'] = 'videos/' . end($parts);
                }
            }
            
            // Fix hls_path
            if ($video->hls_path) {
                // Extract relative path from absolute path
                if (str_contains($video->hls_path, 'videos/')) {
                    $parts = explode('videos/', $video->hls_path);
                    $updates['hls_path'] = 'videos/' . end($parts);
                } elseif (str_contains($video->hls_path, 'temp-videos/')) {
                    $parts = explode('temp-videos/', $video->hls_path);
                    $updates['hls_path'] = 'temp-videos/' . end($parts);
                }
            }
            
            // Fix metadata if it contains absolute paths
            if ($video->metadata) {
                $metadata = json_decode($video->metadata, true);
                if (isset($metadata['copied_file_path'])) {
                    // Extract relative path from absolute path
                    if (str_contains($metadata['copied_file_path'], 'videos/')) {
                        $parts = explode('videos/', $metadata['copied_file_path']);
                        $metadata['copied_file_path'] = 'videos/' . end($parts);
                    } elseif (str_contains($metadata['copied_file_path'], 'temp-videos/')) {
                        $parts = explode('temp-videos/', $metadata['copied_file_path']);
                        $metadata['copied_file_path'] = 'temp-videos/' . end($parts);
                    }
                    $updates['metadata'] = json_encode($metadata);
                }
            }
            
            // Clear any processing errors related to file not found
            if ($video->processing_error && str_contains($video->processing_error, 'Video file not found')) {
                $updates['processing_error'] = null;
                $updates['status'] = 'pending'; // Reset to pending to reprocess
            }
            
            // Update the video record
            if (!empty($updates)) {
                DB::table('videos')
                    ->where('id', $video->id)
                    ->update($updates);
                    
                echo "Fixed video ID: {$video->id}\n";
            }
        }
        
        // Also clean up any videos with localhost paths in error messages
        DB::table('videos')
            ->where('processing_error', 'like', '%/Users/panapat/%')
            ->orWhere('processing_error', 'like', '%localhost%')
            ->update([
                'processing_error' => null,
                'status' => 'pending'
            ]);
            
        echo "Video paths migration completed.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we're converting absolute to relative paths
        // The original absolute paths are lost
    }
};