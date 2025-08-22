<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGarden;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommunityGardenController extends Controller
{
    /**
     * Get current user ID (for testing, use first user)
     */
    private function getCurrentUserId(): string
    {
        // For testing purposes, use first user like other controllers
        // In production, this should use Auth::id()
        $user = User::first();
        return $user ? $user->id : '0198b246-1b0e-7cd6-8f5e-8a0a5b787402';
    }

    /**
     * Get community garden overview and public gardens
     */
    public function getCommunityOverview(): JsonResponse
    {
        try {
            // Get public gardens (top gardens by different criteria)
            $publicGardens = [
                'featured_garden' => [
                    'id' => 'featured-001',
                    'owner_name' => 'ครูแนท',
                    'garden_name' => 'สวนแห่งความสุข',
                    'theme' => 'zen',
                    'level' => 25,
                    'total_plants' => 15,
                    'visitors_today' => 47,
                    'likes' => 156,
                    'description' => 'สวนเซนที่เน้นความสงบและการผ่อนคลาย เหมาะสำหรับการทำสมาธิ',
                    'preview_image' => '/images/gardens/featured-zen.jpg',
                    'special_plants' => ['ลาเวนเดอร์', 'มะลิ', 'ต้นโอ๊ก'],
                    'achievements' => 23,
                    'is_featured' => true
                ],
                'trending_gardens' => [
                    [
                        'id' => 'trend-001',
                        'owner_name' => 'คุณวรดา',
                        'garden_name' => 'สวนแฟนตาซี',
                        'theme' => 'seasonal_spring',
                        'level' => 18,
                        'total_plants' => 12,
                        'visitors_today' => 32,
                        'likes' => 89,
                        'trend_reason' => 'มีผู้เยี่ยมชมเพิ่มขึ้น 200% ในสัปดาห์นี้'
                    ],
                    [
                        'id' => 'trend-002',
                        'owner_name' => 'คุณสมชาย',
                        'garden_name' => 'สวนฟิตเนส',
                        'theme' => 'modern',
                        'level' => 22,
                        'total_plants' => 18,
                        'visitors_today' => 28,
                        'likes' => 76,
                        'trend_reason' => 'พืชประเภทฟิตเนสหลากหลายที่สุด'
                    ]
                ],
                'newest_gardens' => [
                    [
                        'id' => 'new-001',
                        'owner_name' => 'คุณปราณี',
                        'garden_name' => 'สวนมือใหม่',
                        'theme' => 'tropical',
                        'level' => 3,
                        'total_plants' => 4,
                        'visitors_today' => 8,
                        'likes' => 12,
                        'created_days_ago' => 2
                    ],
                    [
                        'id' => 'new-002',
                        'owner_name' => 'คุณธันวา',
                        'garden_name' => 'สวนธรรมชาติ',
                        'theme' => 'cottage',
                        'level' => 5,
                        'total_plants' => 7,
                        'visitors_today' => 15,
                        'likes' => 24,
                        'created_days_ago' => 5
                    ]
                ]
            ];

            // Community stats
            $communityStats = [
                'total_gardens' => 1247,
                'active_gardeners' => 892,
                'plants_growing' => 15683,
                'daily_visitors' => 2341,
                'community_projects' => 8,
                'completed_projects' => 23
            ];

            // Current community projects
            $communityProjects = [
                [
                    'id' => 'project-spring-2024',
                    'name' => 'โครงการสวนฤดูใบไม้ผลิ 2024',
                    'description' => 'ร่วมกันปลูกและดูแลสวนในธีมฤดูใบไม้ผลิ',
                    'goal' => 'ปลูกดอกซากุระ 1,000 ต้น',
                    'progress' => 756,
                    'target' => 1000,
                    'participants' => 234,
                    'days_remaining' => 12,
                    'rewards' => [
                        'xp' => 500,
                        'star_seeds' => 200,
                        'exclusive_plant' => 'ซากุระทอง'
                    ],
                    'status' => 'active'
                ],
                [
                    'id' => 'project-wellness-week',
                    'name' => 'สัปดาห์สุขภาพชุมชน',
                    'description' => 'กิจกรรมส่งเสริมสุขภาพผ่านการทำสวน',
                    'goal' => 'ร่วมกิจกรรมสุขภาพ 500 ครั้ง',
                    'progress' => 423,
                    'target' => 500,
                    'participants' => 156,
                    'days_remaining' => 3,
                    'rewards' => [
                        'xp' => 300,
                        'star_seeds' => 150,
                        'special_badge' => 'นักสุขภาพชุมชน'
                    ],
                    'status' => 'active'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'public_gardens' => $publicGardens,
                    'community_stats' => $communityStats,
                    'community_projects' => $communityProjects,
                    'user_info' => [
                        'can_create_public_garden' => true,
                        'daily_community_visits' => 3,
                        'max_daily_visits' => 5,
                        'community_level' => 2
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลชุมชนได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Visit a public garden
     */
    public function visitPublicGarden(Request $request, string $gardenId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();

            // Mock garden visit data
            $gardenData = [
                'garden_info' => [
                    'id' => $gardenId,
                    'owner_name' => 'ครูแนท',
                    'garden_name' => 'สวนแห่งความสุข',
                    'theme' => 'zen',
                    'level' => 25,
                    'description' => 'สวนเซนที่เน้นความสงบและการผ่อนคลาย',
                    'created_at' => '2024-01-15',
                    'last_updated' => '2024-08-16',
                    'is_public' => true,
                    'visitor_count' => 1247
                ],
                'plants' => [
                    [
                        'id' => 'pub-plant-1',
                        'name' => 'ลาเวนเดอร์แห่งสงบ',
                        'type' => 'ลาเวนเดอร์',
                        'stage' => 4,
                        'stage_name' => 'บานเต็มที่',
                        'health' => 100,
                        'special_effects' => ['กลิ่นหอม', 'ผ่อนคลาย'],
                        'can_water' => true,
                        'position' => ['x' => 2, 'y' => 1]
                    ],
                    [
                        'id' => 'pub-plant-2',
                        'name' => 'มะลิสวรรค์',
                        'type' => 'มะลิ',
                        'stage' => 4,
                        'stage_name' => 'บานเต็มที่',
                        'health' => 95,
                        'special_effects' => ['ดอกสวย', 'กลิ่นหอมเย็น'],
                        'can_water' => false,
                        'position' => ['x' => 3, 'y' => 2]
                    ]
                ],
                'decorations' => [
                    ['type' => 'zen_fountain', 'position' => ['x' => 1, 'y' => 1]],
                    ['type' => 'meditation_stone', 'position' => ['x' => 4, 'y' => 1]],
                    ['type' => 'bamboo_fence', 'position' => ['x' => 0, 'y' => 0]]
                ],
                'visitor_actions' => [
                    'can_like' => true,
                    'can_water_plants' => true,
                    'can_leave_comment' => true,
                    'daily_water_limit' => 3,
                    'waters_used_today' => 1
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $gardenData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเยี่ยมชมสวนได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Like a public garden
     */
    public function likeGarden(Request $request, string $gardenId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();

            // Simulate like action
            $result = [
                'garden_id' => $gardenId,
                'liked' => true,
                'total_likes' => 157, // Previous 156 + 1
                'message' => 'ถูกใจสวนนี้แล้ว! เจ้าของสวนจะได้รับ Star Seeds เพิ่ม',
                'rewards' => [
                    'visitor_xp' => 5,
                    'owner_star_seeds' => 2
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถถูกใจสวนได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Water a plant in public garden
     */
    public function waterPublicPlant(Request $request, string $gardenId, string $plantId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();

            // Simulate watering action
            $result = [
                'plant_id' => $plantId,
                'garden_id' => $gardenId,
                'watered' => true,
                'message' => 'รดน้ำพืชในสวนชุมชนสำเร็จ! ได้รับ XP และ Star Seeds',
                'rewards' => [
                    'visitor_xp' => 10,
                    'visitor_star_seeds' => 3,
                    'owner_notification' => true
                ],
                'plant_status' => [
                    'health_increased' => 5,
                    'new_health' => 100,
                    'next_water_time' => '2024-08-17 17:00:00'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถรดน้ำพืชได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Join a community project
     */
    public function joinCommunityProject(Request $request, string $projectId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'contribution_type' => 'required|string|in:plant,water,visit,share',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ข้อมูลไม่ถูกต้อง',
                    'errors' => $validator->errors()
                ], 400);
            }

            $userId = $this->getCurrentUserId();
            $contributionType = $request->contribution_type;

            // Simulate joining project
            $result = [
                'project_id' => $projectId,
                'joined' => true,
                'contribution_type' => $contributionType,
                'message' => 'เข้าร่วมโครงการชุมชนสำเร็จ! เริ่มช่วยกันทำให้โครงการสำเร็จ',
                'project_progress' => [
                    'old_progress' => 756,
                    'new_progress' => 757,
                    'contribution' => 1,
                    'target' => 1000
                ],
                'rewards' => [
                    'immediate_xp' => 20,
                    'immediate_star_seeds' => 10,
                    'progress_towards_completion' => true
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเข้าร่วมโครงการได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community leaderboard
     */
    public function getCommunityLeaderboard(): JsonResponse
    {
        try {
            $leaderboard = [
                'weekly_top_gardeners' => [
                    [
                        'rank' => 1,
                        'user_name' => 'ครูแนท',
                        'garden_name' => 'สวนแห่งความสุข',
                        'points_this_week' => 2450,
                        'garden_level' => 25,
                        'badge' => 'นักสวนเทพ',
                        'activities' => ['รดน้ำ 45 ครั้ง', 'ปลูกพืช 12 ต้น', 'ช่วยเพื่อน 23 ครั้ง']
                    ],
                    [
                        'rank' => 2,
                        'user_name' => 'คุณวรดา',
                        'garden_name' => 'สวนแฟนตาซี',
                        'points_this_week' => 2180,
                        'garden_level' => 18,
                        'badge' => 'นักสวนเก่ง',
                        'activities' => ['รดน้ำ 38 ครั้ง', 'ปลูกพืช 8 ต้น', 'เยี่ยมเพื่อน 34 ครั้ง']
                    ],
                    [
                        'rank' => 3,
                        'user_name' => 'คุณสมชาย',
                        'garden_name' => 'สวนฟิตเนส',
                        'points_this_week' => 1950,
                        'garden_level' => 22,
                        'badge' => 'นักสุขภาพ',
                        'activities' => ['รดน้ำ 33 ครั้ง', 'ออกกำลังกาย 15 ครั้ง', 'ปลูกพืช 9 ต้น']
                    ]
                ],
                'most_visited_gardens' => [
                    ['garden_name' => 'สวนแห่งความสุข', 'owner' => 'ครูแนท', 'visits' => 234],
                    ['garden_name' => 'สวนแฟนตาซี', 'owner' => 'คุณวรดา', 'visits' => 189],
                    ['garden_name' => 'สวนธรรมชาติ', 'owner' => 'คุณปิยะ', 'visits' => 156]
                ],
                'community_heroes' => [
                    ['name' => 'คุณสมชาย', 'contribution' => 'ช่วยรดน้ำพืชเพื่อน 234 ครั้ง'],
                    ['name' => 'คุณแวว', 'contribution' => 'เป็นที่ปรึกษาสวนให้สมาชิกใหม่'],
                    ['name' => 'ครูป้อม', 'contribution' => 'จัดกิจกรรมชุมชนสวน 12 ครั้ง']
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $leaderboard
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลกระดานคะแนนได้: ' . $e->getMessage()
            ], 500);
        }
    }
}