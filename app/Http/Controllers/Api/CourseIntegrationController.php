<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CourseProgressService;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Enrollment;
use App\Events\LessonCompleted;
use App\Events\CourseCompleted;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CourseIntegrationController extends Controller
{
    protected CourseProgressService $courseProgressService;

    public function __construct(CourseProgressService $courseProgressService)
    {
        $this->courseProgressService = $courseProgressService;
    }

    /**
     * Mark lesson as completed and award garden rewards
     */
    public function completeLessonWithRewards(Request $request, string $lessonId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'watch_time' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid data provided',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Use demo user for testing or authenticated user
            $user = $request->user() ?? User::first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $lesson = Lesson::with('course')->find($lessonId);
            if (!$lesson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lesson not found'
                ], 404);
            }

            DB::beginTransaction();

            // Ensure user is enrolled in the course
            $enrollment = Enrollment::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $lesson->course->id
                ],
                [
                    'enrolled_at' => now(),
                    'progress' => 0.00
                ]
            );

            // Create or update lesson progress
            $lessonProgress = LessonProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id
                ],
                [
                    'enrollment_id' => $enrollment->id,
                    'completed_at' => now(),
                    'is_completed' => true,
                    'watch_time' => $request->input('watch_time', $lesson->duration_minutes ?? 15)
                ]
            );

            // Fire lesson completed event to award garden rewards
            event(new LessonCompleted($user, $lesson, $lessonProgress));

            // Check if this completion triggers course completion
            $courseCompleted = $this->checkCourseCompletion($user, $lesson->course);

            // Check for achievements after lesson completion
            $newAchievements = $this->checkAchievementsAfterLessonCompletion($user);

            DB::commit();

            // Get updated garden info
            $learningProgress = $this->courseProgressService->getUserLearningProgress($user);

            return response()->json([
                'success' => true,
                'message' => 'Lesson completed successfully!',
                'data' => [
                    'lesson_progress' => $lessonProgress,
                    'course_completed' => $courseCompleted,
                    'garden_progress' => $learningProgress,
                    'rewards_info' => 'Garden rewards have been processed automatically',
                    'new_achievements' => $newAchievements
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete lesson with garden rewards', [
                'lesson_id' => $lessonId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete lesson',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's learning progress with garden integration
     */
    public function getLearningProgress(Request $request): JsonResponse
    {
        try {
            // Use demo user for testing or authenticated user
            $user = $request->user() ?? User::first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $learningProgress = $this->courseProgressService->getUserLearningProgress($user);

            return response()->json([
                'success' => true,
                'data' => $learningProgress
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get learning progress', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get learning progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get course completion rewards preview
     */
    public function getCourseRewardsPreview(Request $request, string $courseId): JsonResponse
    {
        try {
            $course = Course::find($courseId);
            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }

            // Calculate potential rewards
            $bonusXp = $this->calculateCourseCompletionBonus($course);
            $bonusStarSeeds = intval($bonusXp * 0.5);

            return response()->json([
                'success' => true,
                'data' => [
                    'course' => [
                        'id' => $course->id,
                        'title' => $course->title,
                        'total_lessons' => $course->total_lessons,
                        'duration_weeks' => $course->duration_weeks
                    ],
                    'completion_rewards' => [
                        'bonus_xp' => $bonusXp,
                        'bonus_star_seeds' => $bonusStarSeeds,
                        'description' => 'Bonus rewards for completing the entire course'
                    ],
                    'potential_achievements' => $this->getPotentialAchievements($course)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get course rewards preview', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get course rewards preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if course is completed and fire completion event
     */
    private function checkCourseCompletion(User $user, Course $course): bool
    {
        // Get all lessons in the course
        $totalLessons = $course->lessons()->count();
        
        // Get completed lessons for this user
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))
            ->where('is_completed', true)
            ->count();

        // Check if all lessons are completed
        if ($completedLessons >= $totalLessons && $totalLessons > 0) {
            // Create or update enrollment as completed
            $enrollment = Enrollment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id
                ],
                [
                    'enrolled_at' => $enrollment->enrolled_at ?? now(),
                    'completed_at' => now(),
                    'progress' => 100.00
                ]
            );

            // Fire course completed event
            event(new CourseCompleted($user, $course, $enrollment));

            return true;
        }

        return false;
    }

    /**
     * Calculate course completion bonus (copy from service for preview)
     */
    private function calculateCourseCompletionBonus(Course $course): int
    {
        $baseBonus = 100;
        $lessonBonus = ($course->total_lessons ?? 5) * 10;
        $durationBonus = ($course->duration_weeks ?? 2) * 25;
        
        return $baseBonus + $lessonBonus + $durationBonus;
    }

    /**
     * Get potential achievements for course completion
     */
    private function getPotentialAchievements(Course $course): array
    {
        $potentialAchievements = [];

        // Add learning achievements
        $potentialAchievements[] = [
            'name' => 'นักเรียนขยัน',
            'description' => 'เรียนจบบทเรียน 10 บท',
            'type' => 'learning'
        ];

        $potentialAchievements[] = [
            'name' => 'ปราชญ์แห่งสุขภาพ',
            'description' => 'เรียนจบคอร์ส 3 คอร์ส',
            'type' => 'learning'
        ];

        // Add category-specific achievements
        if ($course->category) {
            $categoryName = strtolower($course->category->name ?? '');
            
            if (str_contains($categoryName, 'fitness') || str_contains($categoryName, 'ออกกำลัง')) {
                $potentialAchievements[] = [
                    'name' => 'มาราธอนเนอร์',
                    'description' => 'เรียนจบคอร์สฟิตเนส 2 คอร์ส',
                    'type' => 'fitness'
                ];
            }

            if (str_contains($categoryName, 'mental') || str_contains($categoryName, 'จิตใจ')) {
                $potentialAchievements[] = [
                    'name' => 'ชีวิตสมดุล',
                    'description' => 'เรียนจบคอร์สสุขภาพจิต 2 คอร์ส',
                    'type' => 'mental'
                ];
            }
        }

        return $potentialAchievements;
    }

    /**
     * Check and award achievements after lesson completion
     */
    private function checkAchievementsAfterLessonCompletion(User $user): array
    {
        $newAchievements = [];

        // ดึง achievements ที่ยังไม่ได้รับและเกี่ยวกับการเรียน
        $unearned = Achievement::active()
            ->where('category', 'learning')
            ->whereNotIn('id', $user->userAchievements()->pluck('achievement_id'))
            ->get();

        foreach ($unearned as $achievement) {
            // ตรวจสอบเงื่อนไขของแต่ละ achievement
            if ($achievement->checkCriteria($user)) {
                try {
                    $userAchievement = $achievement->awardToUser($user->id);
                    
                    // เพิ่ม XP และ star seeds ให้ garden
                    if ($user->garden) {
                        $user->garden->addXP($achievement->xp_reward);
                        $user->garden->addStarSeeds($achievement->star_seeds_reward);
                    }
                    
                    $newAchievements[] = [
                        'id' => $achievement->id,
                        'name' => $achievement->name,
                        'description' => $achievement->description,
                        'badge_icon' => $achievement->badge_icon,
                        'rarity' => $achievement->rarity,
                        'xp_reward' => $achievement->xp_reward,
                        'star_seeds_reward' => $achievement->star_seeds_reward,
                        'earned_at' => $userAchievement->earned_at->format('Y-m-d H:i:s')
                    ];
                } catch (\Exception $e) {
                    // Achievement already earned, skip
                }
            }
        }

        return $newAchievements;
    }
}