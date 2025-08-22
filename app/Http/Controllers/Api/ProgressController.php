<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function getMySummary(Request $request)
    {
        try {
            // Get user from token (simple implementation)
            $token = $request->header('Authorization');
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No token provided',
                    'summary' => []
                ]);
            }

            // Remove "Bearer " prefix if present
            $token = str_replace('Bearer ', '', $token);
            
            // Decode token
            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format',
                    'summary' => []
                ]);
            }

            $userId = $parts[0];

            // Get overall progress summary
            $totalCourses = Course::count();
            $totalLessons = Lesson::count();
            $completedLessons = LessonProgress::where('user_id', $userId)
                ->where('is_completed', true)
                ->count();

            // Calculate courses progress
            $coursesProgress = Course::select('id', 'title', 'description')
                ->take(10)
                ->get()
                ->map(function ($course) use ($userId) {
                    $totalLessons = Lesson::where('course_id', $course->id)->count();
                    $completedLessons = LessonProgress::where('user_id', $userId)
                        ->whereIn('lesson_id', function($query) use ($course) {
                            $query->select('id')
                                  ->from('lessons')
                                  ->where('course_id', $course->id);
                        })
                        ->where('is_completed', true)
                        ->count();

                    $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

                    return [
                        'course_id' => $course->id,
                        'course_title' => $course->title,
                        'total_lessons' => $totalLessons,
                        'completed_lessons' => $completedLessons,
                        'progress_percentage' => $progress,
                        'status' => $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'not_started')
                    ];
                });

            // Calculate recent activity (last 7 days)
            $recentActivity = LessonProgress::where('user_id', $userId)
                ->where('is_completed', true)
                ->where('updated_at', '>=', now()->subDays(7))
                ->count();

            // Calculate study streak
            $studyStreak = 0;
            $currentDate = now()->startOfDay();
            for ($i = 0; $i < 30; $i++) {
                $hasActivityOnDate = LessonProgress::where('user_id', $userId)
                    ->where('is_completed', true)
                    ->whereDate('updated_at', $currentDate->copy()->subDays($i))
                    ->exists();
                
                if ($hasActivityOnDate) {
                    $studyStreak++;
                } else {
                    break;
                }
            }

            // Get learning statistics
            $weeklyStats = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $lessonsCompleted = LessonProgress::where('user_id', $userId)
                    ->where('is_completed', true)
                    ->whereDate('updated_at', $date)
                    ->count();
                
                $weeklyStats[] = [
                    'date' => $date->format('Y-m-d'),
                    'day_name' => $date->format('l'),
                    'lessons_completed' => $lessonsCompleted
                ];
            }

            $overallProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

            return response()->json([
                'success' => true,
                'summary' => [
                    'overall_progress' => [
                        'total_courses' => $totalCourses,
                        'total_lessons' => $totalLessons,
                        'completed_lessons' => $completedLessons,
                        'progress_percentage' => $overallProgress
                    ],
                    'study_streak' => [
                        'current_streak' => $studyStreak,
                        'unit' => 'days'
                    ],
                    'recent_activity' => [
                        'lessons_this_week' => $recentActivity,
                        'week_start' => now()->subDays(7)->format('Y-m-d'),
                        'week_end' => now()->format('Y-m-d')
                    ],
                    'weekly_stats' => $weeklyStats,
                    'courses_progress' => $coursesProgress->toArray(),
                    'achievements' => [
                        'total_completed_courses' => $coursesProgress->where('status', 'completed')->count(),
                        'courses_in_progress' => $coursesProgress->where('status', 'in_progress')->count(),
                        'courses_not_started' => $coursesProgress->where('status', 'not_started')->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch progress summary: ' . $e->getMessage(),
                'summary' => []
            ], 500);
        }
    }

    public function updateLessonProgress(Request $request, $lessonId)
    {
        try {
            $request->validate([
                'is_completed' => 'required|boolean',
                'watch_time' => 'nullable|integer|min:0'
            ]);

            // Get user from token
            $token = $request->header('Authorization');
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No token provided'
                ]);
            }

            $token = str_replace('Bearer ', '', $token);
            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format'
                ]);
            }

            $userId = $parts[0];

            // Check if lesson exists
            $lesson = Lesson::find($lessonId);
            if (!$lesson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lesson not found'
                ], 404);
            }

            // Update or create lesson progress
            $progress = LessonProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'lesson_id' => $lessonId
                ],
                [
                    'is_completed' => $request->is_completed,
                    'watch_time' => $request->watch_time ?? 0,
                    'completed_at' => $request->is_completed ? now() : null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully',
                'progress' => [
                    'lesson_id' => $progress->lesson_id,
                    'is_completed' => $progress->is_completed,
                    'watch_time' => $progress->watch_time,
                    'completed_at' => $progress->completed_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $progress->updated_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCourseProgress(Request $request, $courseId)
    {
        try {
            // Get user from token
            $token = $request->header('Authorization');
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No token provided'
                ]);
            }

            $token = str_replace('Bearer ', '', $token);
            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format'
                ]);
            }

            $userId = $parts[0];

            // Check if course exists
            $course = Course::with('instructor:id,name')->find($courseId);
            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }

            // Get course lessons with progress
            $lessons = Lesson::where('course_id', $courseId)
                ->select('id', 'title', 'description', 'duration')
                ->get()
                ->map(function ($lesson) use ($userId) {
                    $progress = LessonProgress::where('user_id', $userId)
                        ->where('lesson_id', $lesson->id)
                        ->first();

                    return [
                        'lesson_id' => $lesson->id,
                        'title' => $lesson->title,
                        'description' => $lesson->description,
                        'duration' => $lesson->duration,
                        'is_completed' => $progress ? $progress->is_completed : false,
                        'watch_time' => $progress ? $progress->watch_time : 0,
                        'completed_at' => $progress && $progress->completed_at 
                            ? $progress->completed_at->format('Y-m-d H:i:s') 
                            : null,
                        'progress_percentage' => $progress && $lesson->duration > 0 
                            ? round(($progress->watch_time / $lesson->duration) * 100) 
                            : 0
                    ];
                });

            $totalLessons = $lessons->count();
            $completedLessons = $lessons->where('is_completed', true)->count();
            $courseProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

            return response()->json([
                'success' => true,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'instructor' => $course->instructor ? $course->instructor->name : 'Unknown',
                    'level' => $course->level
                ],
                'progress' => [
                    'total_lessons' => $totalLessons,
                    'completed_lessons' => $completedLessons,
                    'progress_percentage' => $courseProgress,
                    'status' => $courseProgress >= 100 ? 'completed' : ($courseProgress > 0 ? 'in_progress' : 'not_started')
                ],
                'lessons' => $lessons->toArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch course progress: ' . $e->getMessage()
            ], 500);
        }
    }
}