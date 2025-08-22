<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoUploadController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\GardenController;
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\CourseIntegrationController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\GardenThemeController;
use App\Http\Controllers\Api\CommunityGardenController;
use App\Http\Controllers\Api\AdvancedPlantController;
use App\Http\Controllers\Api\SeasonalEventController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ProgressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/user', function (Request $request) {
    return response()->json(['message' => 'User endpoint working']);
});

// Authentication routes (public)
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

    Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'profile']);
});

// Public API routes (no authentication required)
Route::prefix('v1')->group(function () {
    
    // User management routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/stats', [UserController::class, 'stats']);
        Route::get('/{id}', [UserController::class, 'show']);
    });
    
    // Course and Lesson routes
    Route::get('/courses', [LessonController::class, 'getCourses']);
    Route::get('/courses/{courseId}/lessons', [LessonController::class, 'getCourseLessons']);
    Route::get('/lessons/{lessonId}', [LessonController::class, 'show']);
    Route::get('/lessons/{lessonId}/stream-url', [LessonController::class, 'getStreamUrl']);
    
    // Enrollment routes
    Route::get('/enrollments/my', [EnrollmentController::class, 'getMyEnrollments']);
    Route::post('/enrollments', [EnrollmentController::class, 'enrollInCourse']);
    
    // Progress tracking routes
    Route::get('/progress/my-summary', [ProgressController::class, 'getMySummary']);
    Route::put('/progress/lessons/{lessonId}', [ProgressController::class, 'updateLessonProgress']);
    Route::get('/progress/courses/{courseId}', [ProgressController::class, 'getCourseProgress']);
    
    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'service' => 'BoostMe Admin API',
            'version' => '1.0.0'
        ]);
    });
});

// Video-related routes
Route::prefix('video')->group(function () {
    // Public routes for video streaming (with token validation)
    Route::get('/stream/{video}', [VideoUploadController::class, 'stream'])->name('api.video.stream');
    Route::get('/status/{video}', [VideoUploadController::class, 'status']);
    
    // Protected routes (authentication disabled for testing)
    Route::post('/upload', [VideoUploadController::class, 'upload']);
    Route::get('/{video}/generate-url', [VideoUploadController::class, 'generateStreamUrl']);
    Route::put('/access-log/{log}', [VideoUploadController::class, 'updateAccessLog']);
});

// Wellness Garden API Routes (Public for testing - in production should be protected with auth)
Route::prefix('v1/garden')->group(function () {
    
    // Garden management
    Route::get('/my-garden', [GardenController::class, 'index']);
    Route::put('/water-garden', [GardenController::class, 'waterGarden']);
    
    // Plant management
    Route::get('/plant-types', [GardenController::class, 'getPlantTypes']);
    Route::post('/plant/{plantTypeId}', [GardenController::class, 'plantSeed']);
    Route::put('/plants/{userPlantId}/water', [GardenController::class, 'waterPlant']);
    Route::post('/plants/{userPlantId}/harvest', [GardenController::class, 'harvestPlant']);
    
    // Achievements
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/achievements/category/{category}', [AchievementController::class, 'getByCategory']);
    Route::post('/achievements/check', [AchievementController::class, 'checkAchievements']);
    Route::get('/achievements/my-achievements', [AchievementController::class, 'getUserAchievements']);
    
    // Daily Challenges
    Route::get('/challenges/today', [ChallengeController::class, 'getTodayChallenges']);
    Route::get('/challenges/history', [ChallengeController::class, 'getChallengeHistory']);
    Route::put('/challenges/{challengeId}/progress', [ChallengeController::class, 'updateProgress']);
    Route::get('/challenges/leaderboard', [ChallengeController::class, 'getLeaderboard']);
});

// Course Integration with Garden (Public for testing - in production should be protected with auth)
Route::prefix('v1/course-integration')->group(function () {
    
    // Lesson completion with automatic garden rewards
    Route::post('/lessons/{lessonId}/complete', [CourseIntegrationController::class, 'completeLessonWithRewards']);
    
    // Learning progress with garden integration
    Route::get('/learning-progress', [CourseIntegrationController::class, 'getLearningProgress']);
    
    // Course completion rewards preview
    Route::get('/courses/{courseId}/rewards-preview', [CourseIntegrationController::class, 'getCourseRewardsPreview']);
});

// Friend System API Routes (Public for testing - in production should be protected with auth)
Route::prefix('v1/garden/friends')->group(function () {
    
    // Friend management
    Route::get('/', [FriendController::class, 'getFriendsList']);
    Route::get('/pending', [FriendController::class, 'getPendingRequests']);
    Route::post('/request', [FriendController::class, 'sendFriendRequest']);
    Route::put('/accept/{requestId}', [FriendController::class, 'acceptFriendRequest']);
    Route::delete('/reject/{requestId}', [FriendController::class, 'rejectFriendRequest']);
    Route::delete('/remove/{friendId}', [FriendController::class, 'removeFriend']);
    
    // Friend garden interactions
    Route::get('/{friendId}/garden', [FriendController::class, 'visitFriendGarden']);
    Route::post('/{friendId}/plants/{plantId}/water', [FriendController::class, 'helpWaterPlant']);
    
    // User search for adding friends
    Route::get('/search', [FriendController::class, 'searchUsers']);
});

// Garden Theme System API Routes (Public for testing - in production should be protected with auth)
Route::prefix('v1/garden/themes')->group(function () {
    
    // Theme management
    Route::get('/', [GardenThemeController::class, 'getAvailableThemes']);
    Route::get('/current', [GardenThemeController::class, 'getCurrentTheme']);
    Route::post('/apply', [GardenThemeController::class, 'applyTheme']);
});

// Community Garden API Routes (Public for testing - in production should be protected with auth)
Route::prefix('v1/garden/community')->group(function () {
    
    // Community overview and discovery
    Route::get('/', [CommunityGardenController::class, 'getCommunityOverview']);
    Route::get('/leaderboard', [CommunityGardenController::class, 'getCommunityLeaderboard']);
    
    // Public garden interactions
    Route::get('/gardens/{gardenId}', [CommunityGardenController::class, 'visitPublicGarden']);
    Route::post('/gardens/{gardenId}/like', [CommunityGardenController::class, 'likeGarden']);
    Route::post('/gardens/{gardenId}/plants/{plantId}/water', [CommunityGardenController::class, 'waterPublicPlant']);
    
    // Community projects
    Route::post('/projects/{projectId}/join', [CommunityGardenController::class, 'joinCommunityProject']);
});

// Advanced Plant System API Routes (Public for testing - in production should be protected with auth)
Route::prefix('v1/garden/advanced-plants')->group(function () {
    
    // Plant special abilities
    Route::get('/{plantId}/abilities', [AdvancedPlantController::class, 'getPlantAbilities']);
    Route::post('/{plantId}/activate-ability', [AdvancedPlantController::class, 'activateAbility']);
    
    // Plant evolution
    Route::post('/{plantId}/evolve', [AdvancedPlantController::class, 'evolvePlant']);
    
    // Plant breeding
    Route::post('/breed', [AdvancedPlantController::class, 'breedPlants']);
});

// Seasonal Events System API Routes (Public for testing - in production should be protected with auth)
Route::prefix('v1/garden/seasonal')->group(function () {
    
    // Current events and weather
    Route::get('/events', [SeasonalEventController::class, 'getCurrentEvents']);
    Route::get('/weather', [SeasonalEventController::class, 'getWeatherStatus']);
    
    // Event participation
    Route::post('/events/{eventId}/participate', [SeasonalEventController::class, 'participateEvent']);
    
    // Seasonal plants
    Route::post('/plants/activate', [SeasonalEventController::class, 'activateSeasonalPlant']);
});