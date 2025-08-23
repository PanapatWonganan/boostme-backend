<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Course;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * Get authenticated user from token
     */
    private function getAuthenticatedUser(Request $request)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix
        
        try {
            // Decode base64 token (same format as garden authentication)
            $decoded = base64_decode($token);
            if (!$decoded || !str_contains($decoded, '|')) {
                return null;
            }

            list($userId, $tokenHash) = explode('|', $decoded, 2);
            
            // Find user
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return null;
            }

            return $user;
        } catch (\Exception $e) {
            \Log::warning('Authentication failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Return authentication error response
     */
    private function authError($message = 'Authentication required')
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 401);
    }

    /**
     * Get all lessons for a course
     */
    public function getCourseLessons($courseId)
    {
        $course = Course::with(['lessons' => function($query) {
            $query->with('primaryVideo')
                  ->orderBy('order_index', 'asc');
        }])->findOrFail($courseId);
        
        $lessons = $course->lessons->map(function ($lesson) {
            $video = $lesson->primaryVideo;
            
            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'duration_minutes' => $lesson->duration_minutes,
                'order_index' => $lesson->order_index,
                'is_free' => $lesson->is_free,
                'video_url' => $lesson->video_url, // External URL if any
                'video' => $video ? [
                    'id' => $video->id,
                    'status' => $video->status,
                    'duration' => $video->formatted_duration,
                    'size' => $video->formatted_size,
                    'ready' => $video->isReady(),
                ] : null,
                'created_at' => $lesson->created_at,
            ];
        });
        
        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
            ],
            'lessons' => $lessons,
            'total_lessons' => $lessons->count()
        ]);
    }
    
    /**
     * Get single lesson with video details
     */
    public function show($lessonId)
    {
        $lesson = Lesson::with(['primaryVideo', 'course'])->findOrFail($lessonId);
        $video = $lesson->primaryVideo;
        
        $lessonData = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'duration_minutes' => $lesson->duration_minutes,
            'order_index' => $lesson->order_index,
            'is_free' => $lesson->is_free,
            'video_url' => $lesson->video_url,
            'course' => [
                'id' => $lesson->course->id,
                'title' => $lesson->course->title,
            ],
            'video' => null,
            'can_watch' => $lesson->is_free, // In production, check user's purchase status
        ];
        
        if ($video) {
            $lessonData['video'] = [
                'id' => $video->id,
                'status' => $video->status,
                'duration' => $video->formatted_duration,
                'size' => $video->formatted_size,
                'ready' => $video->isReady(),
                'processing_error' => $video->processing_error,
            ];
            
            // If video is ready and user can watch, provide streaming info
            if ($video->isReady() && $lessonData['can_watch']) {
                $lessonData['video']['stream_available'] = true;
            }
        }
        
        return response()->json($lessonData);
    }
    
    /**
     * Get streaming URL for a lesson's video
     */
    public function getStreamUrl(Request $request, $lessonId)
    {        
        $lesson = Lesson::with('primaryVideo')->findOrFail($lessonId);
        $video = $lesson->primaryVideo;
        
        if (!$video || !$video->isReady()) {
            return response()->json([
                'message' => 'Video not available',
                'status' => $video ? $video->status : 'no_video'
            ], 400);
        }
        
        // Check if user can watch (for now, allow free lessons)
        if (!$lesson->is_free) {
            // TODO: Check if user has purchased the course
            return response()->json([
                'message' => 'This lesson requires course purchase'
            ], 403);
        }
        
        // Generate signed URL - use anonymous for free lessons for now
        $expires = now()->addHours(2);
        $userId = 'anonymous';
        
        // Try to get authenticated user if available
        $user = $this->getAuthenticatedUser($request);
        if ($user) {
            $userId = $user->id;
        }
        
        $token = hash_hmac(
            'sha256',
            "{$video->id}:{$userId}:{$expires->timestamp}",
            config('app.key')
        );
        
        $streamUrl = route('api.video.stream', [
            'video' => $video->id,
            'user' => $userId,
            'expires' => $expires->timestamp,
            'token' => $token
        ]);
        
        return response()->json([
            'stream_url' => $streamUrl,
            'expires_at' => $expires->toISOString(),
            'video' => [
                'id' => $video->id,
                'title' => $lesson->title,
                'duration' => $video->formatted_duration,
            ],
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
            ]
        ]);
    }
    
    /**
     * Get all courses with lesson counts
     */
    public function getCourses()
    {
        $courses = Course::withCount('lessons')
            ->with(['lessons' => function($query) {
                $query->where('is_free', true)
                      ->orderBy('order_index', 'asc')
                      ->limit(1);
            }])
            ->get()
            ->map(function($course) {
                $freeLesson = $course->lessons->first();
                
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'instructor' => $course->instructor_name ?? 'Unknown',
                    'price' => $course->price,
                    'level' => $course->level,
                    'lessons_count' => $course->lessons_count,
                    'duration_minutes' => $course->duration_minutes,
                    'free_preview' => $freeLesson ? [
                        'id' => $freeLesson->id,
                        'title' => $freeLesson->title,
                    ] : null,
                    'created_at' => $course->created_at,
                ];
            });
            
        return response()->json([
            'courses' => $courses,
            'total' => $courses->count()
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}