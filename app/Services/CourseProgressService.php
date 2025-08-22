<?php

namespace App\Services;

use App\Models\User;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Enrollment;
use App\Models\UserGarden;
use App\Models\GardenActivity;
use App\Models\Achievement;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseProgressService
{
    /**
     * Award garden XP when a lesson is completed
     */
    public function onLessonCompleted(User $user, Lesson $lesson): array
    {
        $rewards = [
            'xp' => 0,
            'star_seeds' => 0,
            'achievements' => []
        ];

        try {
            DB::beginTransaction();

            // Get or create user's garden
            $garden = UserGarden::getOrCreateGarden($user);

            // Calculate XP based on lesson duration and category
            $baseXp = $this->calculateLessonXp($lesson);
            $starSeeds = intval($baseXp * 0.3); // 30% of XP as Star Seeds

            // Award XP to garden
            $garden->addXp($baseXp);
            $garden->star_seeds += $starSeeds;
            $garden->save();

            // Log garden activity
            GardenActivity::create([
                'user_id' => $user->id,
                'garden_id' => $garden->id,
                'activity_type' => 'lesson_completed',
                'target_type' => 'lesson',
                'target_id' => $lesson->id,
                'xp_earned' => $baseXp,
                'star_seeds_earned' => $starSeeds,
                'metadata' => [
                    'lesson_title' => $lesson->title,
                    'course_title' => $lesson->course->title,
                    'duration_minutes' => $lesson->duration_minutes
                ]
            ]);

            $rewards['xp'] = $baseXp;
            $rewards['star_seeds'] = $starSeeds;

            // Check for achievements
            $newAchievements = $this->checkLearningAchievements($user);
            $rewards['achievements'] = $newAchievements;

            DB::commit();

            Log::info('Garden rewards awarded for lesson completion', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'xp' => $baseXp,
                'star_seeds' => $starSeeds,
                'achievements' => count($newAchievements)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to award garden rewards for lesson completion', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $rewards;
    }

    /**
     * Award bonus XP when a course is completed
     */
    public function onCourseCompleted(User $user, Course $course): array
    {
        $rewards = [
            'xp' => 0,
            'star_seeds' => 0,
            'achievements' => []
        ];

        try {
            DB::beginTransaction();

            // Get user's garden
            $garden = UserGarden::getOrCreateGarden($user);

            // Calculate course completion bonus
            $bonusXp = $this->calculateCourseCompletionBonus($course);
            $bonusStarSeeds = intval($bonusXp * 0.5); // 50% bonus for course completion

            // Award bonus XP
            $garden->addXp($bonusXp);
            $garden->star_seeds += $bonusStarSeeds;
            $garden->save();

            // Log garden activity
            GardenActivity::create([
                'user_id' => $user->id,
                'garden_id' => $garden->id,
                'activity_type' => 'course_completed',
                'target_type' => 'course',
                'target_id' => $course->id,
                'xp_earned' => $bonusXp,
                'star_seeds_earned' => $bonusStarSeeds,
                'metadata' => [
                    'course_title' => $course->title,
                    'total_lessons' => $course->total_lessons,
                    'duration_weeks' => $course->duration_weeks
                ]
            ]);

            $rewards['xp'] = $bonusXp;
            $rewards['star_seeds'] = $bonusStarSeeds;

            // Check for course-related achievements
            $newAchievements = $this->checkCourseAchievements($user, $course);
            $rewards['achievements'] = $newAchievements;

            DB::commit();

            Log::info('Garden bonus rewards awarded for course completion', [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'bonus_xp' => $bonusXp,
                'bonus_star_seeds' => $bonusStarSeeds,
                'achievements' => count($newAchievements)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to award garden bonus for course completion', [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $rewards;
    }

    /**
     * Calculate XP based on lesson duration and type
     */
    private function calculateLessonXp(Lesson $lesson): int
    {
        // Base XP calculation
        $baseXp = 20; // Minimum XP per lesson
        
        // Duration bonus (1 XP per minute, capped at 60 minutes)
        $durationBonus = min($lesson->duration_minutes ?? 15, 60);
        
        // Category bonus based on course category
        $categoryBonus = $this->getCategoryBonus($lesson->course);
        
        return $baseXp + $durationBonus + $categoryBonus;
    }

    /**
     * Calculate course completion bonus
     */
    private function calculateCourseCompletionBonus(Course $course): int
    {
        // Base bonus
        $baseBonus = 100;
        
        // Lesson count bonus (10 XP per lesson)
        $lessonBonus = ($course->total_lessons ?? 5) * 10;
        
        // Duration bonus (25 XP per week)
        $durationBonus = ($course->duration_weeks ?? 2) * 25;
        
        return $baseBonus + $lessonBonus + $durationBonus;
    }

    /**
     * Get category-specific XP bonus
     */
    private function getCategoryBonus(Course $course): int
    {
        if (!$course->category) {
            return 0;
        }

        $categoryBonuses = [
            'fitness' => 15,        // Higher bonus for fitness
            'nutrition' => 12,      // Medium bonus for nutrition  
            'mental-health' => 10,  // Mental health bonus
            'pregnancy' => 15,      // High bonus for pregnancy content
            'postpartum' => 12,     // Postpartum recovery
            'hormonal' => 10,       // Hormonal balance
        ];

        $categoryName = strtolower($course->category->name ?? '');
        
        foreach ($categoryBonuses as $key => $bonus) {
            if (str_contains($categoryName, $key) || str_contains($categoryName, str_replace('-', '', $key))) {
                return $bonus;
            }
        }

        return 5; // Default small bonus
    }

    /**
     * Check for learning-related achievements
     */
    private function checkLearningAchievements(User $user): array
    {
        $newAchievements = [];

        // Count completed lessons
        $completedLessonsCount = LessonProgress::where('user_id', $user->id)
            ->where('is_completed', true)
            ->count();

        // Count completed courses
        $completedCoursesCount = Enrollment::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();

        // Achievement criteria
        $achievementCriteria = [
            // Learning achievements
            [
                'name' => 'นักเรียนขยัน',
                'type' => 'lessons',
                'threshold' => 10,
                'current' => $completedLessonsCount
            ],
            [
                'name' => 'ปราชญ์แห่งสุขภาพ',
                'type' => 'courses',
                'threshold' => 3,
                'current' => $completedCoursesCount
            ],
            [
                'name' => 'นักปลูกมือใหม่',
                'type' => 'lessons',
                'threshold' => 1,
                'current' => $completedLessonsCount
            ]
        ];

        foreach ($achievementCriteria as $criteria) {
            if ($criteria['current'] >= $criteria['threshold']) {
                $achievement = Achievement::where('name', $criteria['name'])->first();
                
                if ($achievement) {
                    // Check if user already has this achievement
                    $hasAchievement = UserAchievement::where('user_id', $user->id)
                        ->where('achievement_id', $achievement->id)
                        ->exists();

                    if (!$hasAchievement) {
                        // Award the achievement
                        $userAchievement = UserAchievement::create([
                            'user_id' => $user->id,
                            'achievement_id' => $achievement->id,
                            'earned_at' => now(),
                            'progress_data' => [
                                'criteria' => $criteria['type'],
                                'threshold' => $criteria['threshold'],
                                'achieved_value' => $criteria['current']
                            ]
                        ]);

                        // Award achievement rewards to garden
                        $garden = UserGarden::getOrCreateGarden($user);
                        $garden->addXp($achievement->xp_reward);
                        $garden->star_seeds += $achievement->star_seeds_reward;
                        $garden->save();

                        $newAchievements[] = [
                            'achievement' => $achievement,
                            'user_achievement' => $userAchievement
                        ];

                        Log::info('Learning achievement awarded', [
                            'user_id' => $user->id,
                            'achievement' => $achievement->name,
                            'criteria' => $criteria
                        ]);
                    }
                }
            }
        }

        return $newAchievements;
    }

    /**
     * Check for course-specific achievements
     */
    private function checkCourseAchievements(User $user, Course $course): array
    {
        $newAchievements = [];

        // Get user's completed courses by category
        $completedCoursesByCategory = Enrollment::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with('course.category')
            ->get()
            ->groupBy(function ($enrollment) {
                return $enrollment->course->category->name ?? 'other';
            });

        // Category-specific achievements
        $categoryAchievements = [
            'fitness' => ['threshold' => 2, 'achievement' => 'มาราธอนเนอร์'],
            'mental-health' => ['threshold' => 2, 'achievement' => 'ชีวิตสมดุล'],
        ];

        foreach ($categoryAchievements as $category => $data) {
            $categoryName = strtolower($course->category->name ?? '');
            
            if (str_contains($categoryName, $category) || str_contains($categoryName, str_replace('-', '', $category))) {
                $coursesInCategory = $completedCoursesByCategory->get($course->category->name, collect());
                
                if ($coursesInCategory->count() >= $data['threshold']) {
                    $achievement = Achievement::where('name', $data['achievement'])->first();
                    
                    if ($achievement) {
                        // Check if user already has this achievement
                        $hasAchievement = UserAchievement::where('user_id', $user->id)
                            ->where('achievement_id', $achievement->id)
                            ->exists();

                        if (!$hasAchievement) {
                            // Award the achievement
                            $userAchievement = UserAchievement::create([
                                'user_id' => $user->id,
                                'achievement_id' => $achievement->id,
                                'earned_at' => now(),
                                'progress_data' => [
                                    'category' => $category,
                                    'courses_completed' => $coursesInCategory->count(),
                                    'threshold' => $data['threshold']
                                ]
                            ]);

                            // Award achievement rewards to garden
                            $garden = UserGarden::getOrCreateGarden($user);
                            $garden->addXp($achievement->xp_reward);
                            $garden->star_seeds += $achievement->star_seeds_reward;
                            $garden->save();

                            $newAchievements[] = [
                                'achievement' => $achievement,
                                'user_achievement' => $userAchievement
                            ];
                        }
                    }
                }
            }
        }

        return $newAchievements;
    }

    /**
     * Get user's learning progress for garden display
     */
    public function getUserLearningProgress(User $user): array
    {
        $garden = UserGarden::where('user_id', $user->id)->first();
        
        return [
            'garden_level' => $garden?->level ?? 1,
            'garden_xp' => $garden?->xp ?? 0,
            'star_seeds' => $garden?->star_seeds ?? 0,
            'completed_lessons' => LessonProgress::where('user_id', $user->id)
                ->where('is_completed', true)
                ->count(),
            'completed_courses' => Enrollment::where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->count(),
            'total_learning_xp' => GardenActivity::where('user_id', $user->id)
                ->whereIn('activity_type', ['lesson_completed', 'course_completed'])
                ->sum('xp_earned'),
            'recent_learning_activities' => GardenActivity::where('user_id', $user->id)
                ->whereIn('activity_type', ['lesson_completed', 'course_completed'])
                ->with(['target'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];
    }
}