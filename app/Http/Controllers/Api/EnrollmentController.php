<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function getMyEnrollments(Request $request)
    {
        try {
            // Get user from token (simple implementation)
            $token = $request->header('Authorization');
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No token provided',
                    'enrollments' => []
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
                    'enrollments' => []
                ]);
            }

            $userId = $parts[0];

            // Get all courses (simulate enrollment - everyone enrolled in all courses)
            $courses = Course::select('id', 'title', 'description', 'instructor', 'price', 'level')
                ->take(10)
                ->get()
                ->map(function ($course) use ($userId) {
                    // Calculate progress
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
                        'id' => $course->id,
                        'title' => $course->title,
                        'description' => $course->description,
                        'instructor' => $course->instructor,
                        'level' => $course->level,
                        'progress' => $progress,
                        'total_lessons' => $totalLessons,
                        'completed_lessons' => $completedLessons,
                        'enrolled_at' => now()->format('Y-m-d H:i:s'),
                        'status' => $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'not_started')
                    ];
                });

            return response()->json([
                'success' => true,
                'enrollments' => $courses,
                'total_enrolled' => $courses->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrollments: ' . $e->getMessage(),
                'enrollments' => []
            ], 500);
        }
    }

    public function enrollInCourse(Request $request)
    {
        try {
            $request->validate([
                'course_id' => 'required|exists:courses,id'
            ]);

            // In a real app, you'd create enrollment records
            // For now, just return success (auto-enrollment system)

            $course = Course::find($request->course_id);

            return response()->json([
                'success' => true,
                'message' => 'Enrolled successfully in ' . $course->title,
                'enrollment' => [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'enrolled_at' => now()->format('Y-m-d H:i:s'),
                    'status' => 'active'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment failed: ' . $e->getMessage()
            ], 500);
        }
    }
}