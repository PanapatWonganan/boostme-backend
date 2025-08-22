<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\UserAchievement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AchievementController extends Controller
{
    /**
     * Get all achievements with user's progress
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? \App\Models\User::first();
            
            $achievements = Achievement::active()
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get()
                ->map(function($achievement) use ($user) {
                    $userAchievement = $user->userAchievements()
                        ->where('achievement_id', $achievement->id)
                        ->first();

                    return [
                        'id' => $achievement->id,
                        'name' => $achievement->name,
                        'category' => $achievement->category,
                        'description' => $achievement->description,
                        'badge_icon' => $achievement->badge_icon,
                        'rarity' => $achievement->rarity,
                        'xp_reward' => $achievement->xp_reward,
                        'star_seeds_reward' => $achievement->star_seeds_reward,
                        'criteria' => $achievement->criteria,
                        'is_earned' => $userAchievement !== null,
                        'earned_at' => $userAchievement?->earned_at?->format('Y-m-d H:i:s'),
                        'progress_data' => $userAchievement?->progress_data ?? []
                    ];
                });

            // Group by category
            $groupedAchievements = $achievements->groupBy('category');

            // Stats
            $stats = [
                'total_achievements' => $achievements->count(),
                'earned_achievements' => $achievements->where('is_earned', true)->count(),
                'completion_percentage' => $achievements->count() > 0 
                    ? round(($achievements->where('is_earned', true)->count() / $achievements->count()) * 100, 2)
                    : 0,
                'total_xp_from_achievements' => $user->userAchievements()
                    ->with('achievement')
                    ->get()
                    ->sum(function($userAchievement) {
                        return $userAchievement->achievement->xp_reward ?? 0;
                    }),
                'recent_achievements' => $user->userAchievements()
                    ->with('achievement')
                    ->recent(30)
                    ->take(5)
                    ->get()
                    ->map(function($userAchievement) {
                        return [
                            'name' => $userAchievement->achievement->name,
                            'badge_icon' => $userAchievement->achievement->badge_icon,
                            'earned_at' => $userAchievement->earned_at->format('Y-m-d H:i:s'),
                            'time_ago' => $userAchievement->earned_at_human
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $groupedAchievements,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get achievements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get achievements by category
     */
    public function getByCategory(Request $request, string $category): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? \App\Models\User::first();
            
            $achievements = Achievement::active()
                ->byCategory($category)
                ->orderBy('sort_order')
                ->get()
                ->map(function($achievement) use ($user) {
                    $userAchievement = $user->userAchievements()
                        ->where('achievement_id', $achievement->id)
                        ->first();

                    return [
                        'id' => $achievement->id,
                        'name' => $achievement->name,
                        'description' => $achievement->description,
                        'badge_icon' => $achievement->badge_icon,
                        'rarity' => $achievement->rarity,
                        'xp_reward' => $achievement->xp_reward,
                        'star_seeds_reward' => $achievement->star_seeds_reward,
                        'is_earned' => $userAchievement !== null,
                        'earned_at' => $userAchievement?->earned_at?->format('Y-m-d H:i:s'),
                        'progress_data' => $userAchievement?->progress_data ?? []
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'category' => $category,
                    'achievements' => $achievements
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get achievements by category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check and award achievements for user
     */
    public function checkAchievements(Request $request): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? \App\Models\User::first();
            $newAchievements = [];

            // ดึง achievements ที่ยังไม่ได้รับ
            $unearned = Achievement::active()
                ->whereNotIn('id', $user->userAchievements()->pluck('achievement_id'))
                ->get();

            foreach ($unearned as $achievement) {
                if ($achievement->checkCriteria($user)) {
                    try {
                        $userAchievement = $achievement->awardToUser($user->id);
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

            return response()->json([
                'success' => true,
                'message' => count($newAchievements) > 0 
                    ? 'New achievements earned!'
                    : 'No new achievements',
                'data' => [
                    'new_achievements' => $newAchievements,
                    'count' => count($newAchievements)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check achievements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's earned achievements
     */
    public function getUserAchievements(Request $request): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? \App\Models\User::first();
            
            $userAchievements = $user->userAchievements()
                ->with('achievement')
                ->orderBy('earned_at', 'desc')
                ->get()
                ->map(function($userAchievement) {
                    return [
                        'id' => $userAchievement->id,
                        'achievement' => [
                            'id' => $userAchievement->achievement->id,
                            'name' => $userAchievement->achievement->name,
                            'category' => $userAchievement->achievement->category,
                            'description' => $userAchievement->achievement->description,
                            'badge_icon' => $userAchievement->achievement->badge_icon,
                            'rarity' => $userAchievement->achievement->rarity,
                            'xp_reward' => $userAchievement->achievement->xp_reward,
                            'star_seeds_reward' => $userAchievement->achievement->star_seeds_reward
                        ],
                        'earned_at' => $userAchievement->earned_at->format('Y-m-d H:i:s'),
                        'earned_date' => $userAchievement->earned_date,
                        'time_ago' => $userAchievement->earned_at_human,
                        'progress_data' => $userAchievement->progress_data
                    ];
                });

            // Group by category
            $groupedByCategory = $userAchievements->groupBy('achievement.category');

            // Recent achievements (last 7 days)
            $recentAchievements = $userAchievements->filter(function($achievement) {
                return $achievement['earned_at'] >= now()->subDays(7)->format('Y-m-d H:i:s');
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $userAchievements,
                    'grouped_by_category' => $groupedByCategory,
                    'recent_achievements' => $recentAchievements,
                    'total_count' => $userAchievements->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user achievements',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}