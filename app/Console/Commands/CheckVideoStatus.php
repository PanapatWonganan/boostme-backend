<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Models\Lesson;
use App\Jobs\ProcessVideoJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckVideoStatus extends Command
{
    protected $signature = 'video:check-status {--reprocess : Reprocess pending/failed videos}';
    protected $description = 'Check video processing status and optionally reprocess stuck videos';

    public function handle()
    {
        $this->info('Checking video processing status...');
        
        // Get all videos grouped by status
        $videoStats = Video::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $this->table(['Status', 'Count'], $videoStats->map(function($stat) {
            return [$stat->status, $stat->count];
        })->toArray());

        // Show details for each lesson with video
        $this->info("\nLesson Video Details:");
        $lessons = Lesson::with('primaryVideo')->get();
        
        $lessonData = [];
        foreach ($lessons as $lesson) {
            $video = $lesson->primaryVideo;
            $lessonData[] = [
                'Lesson ID' => substr($lesson->id, 0, 8) . '...',
                'Lesson Title' => strlen($lesson->title) > 30 ? substr($lesson->title, 0, 27) . '...' : $lesson->title,
                'Has Video URL' => $lesson->video_url ? 'Yes' : 'No',
                'Video Status' => $video ? $video->status : 'No Video',
                'Video Size' => $video ? $video->formatted_size : '-',
                'Processing Error' => $video && $video->processing_error ? 'Yes' : 'No'
            ];
        }
        
        $this->table([
            'Lesson ID', 'Lesson Title', 'Has Video URL', 'Video Status', 'Video Size', 'Processing Error'
        ], $lessonData);

        // Show stuck videos that need reprocessing
        $stuckVideos = Video::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<', now()->subMinutes(10)) // Older than 10 minutes
            ->get();

        if ($stuckVideos->count() > 0) {
            $this->warn("\nFound {$stuckVideos->count()} stuck videos (older than 10 minutes):");
            foreach ($stuckVideos as $video) {
                $this->line("- Video ID: {$video->id} (Status: {$video->status}, Created: {$video->created_at->diffForHumans()})");
            }
        }

        // Show failed videos
        $failedVideos = Video::where('status', 'failed')->get();
        if ($failedVideos->count() > 0) {
            $this->error("\nFound {$failedVideos->count()} failed videos:");
            foreach ($failedVideos as $video) {
                $this->line("- Video ID: {$video->id} - Error: " . substr($video->processing_error ?? 'Unknown error', 0, 80));
            }
        }

        // Reprocess option
        if ($this->option('reprocess')) {
            $videosToReprocess = Video::whereIn('status', ['pending', 'failed'])
                ->orWhere(function($query) {
                    $query->where('status', 'processing')
                          ->where('created_at', '<', now()->subMinutes(10));
                })
                ->get();

            if ($videosToReprocess->count() > 0) {
                if ($this->confirm("Reprocess {$videosToReprocess->count()} videos?")) {
                    foreach ($videosToReprocess as $video) {
                        $video->update([
                            'status' => 'pending',
                            'processing_error' => null
                        ]);
                        ProcessVideoJob::dispatch($video);
                        $this->info("Queued video {$video->id} for reprocessing");
                    }
                    $this->info("All videos have been queued for reprocessing!");
                }
            } else {
                $this->info("No videos need reprocessing.");
            }
        }

        return 0;
    }
}
