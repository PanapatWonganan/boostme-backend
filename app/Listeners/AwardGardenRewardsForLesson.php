<?php

namespace App\Listeners;

use App\Events\LessonCompleted;
use App\Services\CourseProgressService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AwardGardenRewardsForLesson
{
    use InteractsWithQueue;

    protected CourseProgressService $courseProgressService;

    /**
     * Create the event listener.
     */
    public function __construct(CourseProgressService $courseProgressService)
    {
        $this->courseProgressService = $courseProgressService;
    }

    /**
     * Handle the event.
     */
    public function handle(LessonCompleted $event): void
    {
        try {
            // Award garden rewards for lesson completion
            $rewards = $this->courseProgressService->onLessonCompleted(
                $event->user, 
                $event->lesson
            );

            Log::info('Garden rewards processed for lesson completion', [
                'user_id' => $event->user->id,
                'lesson_id' => $event->lesson->id,
                'rewards' => $rewards
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process garden rewards for lesson completion', [
                'user_id' => $event->user->id,
                'lesson_id' => $event->lesson->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't fail the job, just log the error
            // The lesson completion should still succeed even if garden rewards fail
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(LessonCompleted $event, \Throwable $exception): void
    {
        Log::error('Garden rewards job failed for lesson completion', [
            'user_id' => $event->user->id,
            'lesson_id' => $event->lesson->id,
            'error' => $exception->getMessage()
        ]);
    }
}