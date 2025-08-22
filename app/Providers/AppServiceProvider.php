<?php

namespace App\Providers;

use App\Events\LessonCompleted;
use App\Events\CourseCompleted;
use App\Listeners\AwardGardenRewardsForLesson;
use App\Listeners\AwardGardenRewardsForCourse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Garden-Course integration event listeners
        Event::listen(
            LessonCompleted::class,
            AwardGardenRewardsForLesson::class,
        );

        Event::listen(
            CourseCompleted::class,
            AwardGardenRewardsForCourse::class,
        );
    }
}
