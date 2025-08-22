<?php

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Services\CourseProgressService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AwardGardenRewardsForCourse
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
    public function handle(CourseCompleted $event): void
    {
        try {
            // Award garden bonus rewards for course completion
            $rewards = $this->courseProgressService->onCourseCompleted(
                $event->user, 
                $event->course
            );

            Log::info('Garden bonus rewards processed for course completion', [
                'user_id' => $event->user->id,
                'course_id' => $event->course->id,
                'rewards' => $rewards
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process garden bonus rewards for course completion', [
                'user_id' => $event->user->id,
                'course_id' => $event->course->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't fail the job, just log the error
            // The course completion should still succeed even if garden rewards fail
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(CourseCompleted $event, \Throwable $exception): void
    {
        Log::error('Garden bonus rewards job failed for course completion', [
            'user_id' => $event->user->id,
            'course_id' => $event->course->id,
            'error' => $exception->getMessage()
        ]);
    }
}