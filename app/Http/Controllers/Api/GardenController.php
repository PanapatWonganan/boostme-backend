<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserGarden;
use App\Models\PlantType;
use App\Models\UserPlant;
use App\Models\Achievement;
use App\Models\DailyChallenge;
use App\Models\GardenActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GardenController extends Controller
{
    /**
     * Get user's garden information
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? User::first();
            
            // สร้างสวนใหม่ถ้ายังไม่มี
            $garden = $user->getOrCreateGarden();
            
            // โหลดข้อมูลพืชทั้งหมดในสวน
            $plants = $garden->plants()
                ->with('plantType')
                ->orderBy('planted_at', 'desc')
                ->get()
                ->map(function($plant) {
                    return [
                        'id' => $plant->id,
                        'name' => $plant->getDisplayName(),
                        'type' => $plant->plantType->name,
                        'category' => $plant->plantType->category,
                        'stage' => $plant->stage,
                        'stage_name' => $plant->getCurrentStageName(),
                        'health' => $plant->health,
                        'growth_progress' => $plant->getGrowthProgress(),
                        'needs_watering' => $plant->needsWatering(),
                        'is_fully_grown' => $plant->is_fully_grown,
                        'can_harvest' => $plant->is_fully_grown && !$plant->harvested_at,
                        'position' => $plant->position,
                        'planted_at' => $plant->planted_at->format('Y-m-d H:i:s'),
                        'next_water_at' => $plant->next_water_at?->format('Y-m-d H:i:s')
                    ];
                });

            // กิจกรรมล่าสุด
            $recentActivities = $garden->activities()
                ->with('user')
                ->recent(7)
                ->take(10)
                ->get()
                ->map(function($activity) {
                    return [
                        'id' => $activity->id,
                        'description' => $activity->description,
                        'icon' => $activity->icon,
                        'color' => $activity->color,
                        'xp_earned' => $activity->xp_earned,
                        'star_seeds_earned' => $activity->star_seeds_earned,
                        'time_ago' => $activity->time_ago,
                        'created_at' => $activity->created_at->format('Y-m-d H:i:s')
                    ];
                });

            // สถิติของสวน
            $stats = [
                'total_plants' => $plants->count(),
                'growing_plants' => $plants->where('is_fully_grown', false)->count(),
                'mature_plants' => $plants->where('is_fully_grown', true)->count(),
                'plants_need_water' => $plants->where('needs_watering', true)->count(),
                'total_xp_today' => $garden->activities()->today()->sum('xp_earned'),
                'total_activities_today' => $garden->activities()->today()->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'garden' => [
                        'id' => $garden->id,
                        'level' => $garden->level,
                        'xp' => $garden->xp,
                        'xp_for_next_level' => $garden->xp_for_next_level,
                        'can_level_up' => $garden->can_level_up,
                        'star_seeds' => $garden->star_seeds,
                        'theme' => $garden->theme,
                        'needs_watering' => $garden->needsWatering(),
                        'last_watered_at' => $garden->last_watered_at?->format('Y-m-d H:i:s')
                    ],
                    'plants' => $plants,
                    'recent_activities' => $recentActivities,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get garden information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available plant types
     */
    public function getPlantTypes(Request $request): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? User::first();
            $garden = $user ? $user->getOrCreateGarden() : null;

            $plantTypes = PlantType::active()
                ->unlockedForLevel($garden ? $garden->level : 1)
                ->get()
                ->map(function($plantType) {
                    return [
                        'id' => $plantType->id,
                        'name' => $plantType->name,
                        'category' => $plantType->category,
                        'rarity' => $plantType->rarity,
                        'description' => $plantType->description,
                        'base_xp_reward' => $plantType->base_xp_reward,
                        'star_seeds_reward' => $plantType->getStarSeedsReward(),
                        'unlock_level' => $plantType->unlock_level,
                        'care_requirements' => $plantType->getCareRequirementsDescription(),
                        'growth_stages' => $plantType->growth_stages,
                        'icon_path' => $plantType->icon_path
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $plantTypes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get plant types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Plant a new plant in garden
     */
    public function plantSeed(Request $request, string $plantTypeId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'custom_name' => 'nullable|string|max:255',
                'position' => 'nullable|array',
                'position.x' => 'nullable|integer|min:0|max:10',
                'position.y' => 'nullable|integer|min:0|max:10'
            ]);

            // For testing - use demo user if no auth
            $user = $request->user() ?? User::first();
            $garden = $user->getOrCreateGarden();

            // ตรวจสอบ PlantType
            $plantType = PlantType::active()->find($plantTypeId);
            if (!$plantType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plant type not found or not available'
                ], 404);
            }

            // ตรวจสอบว่า unlock แล้วหรือไม่
            if (!$plantType->canBeUnlockedByLevel($garden->level)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plant type not unlocked for your garden level'
                ], 403);
            }

            // ตรวจสอบ Star Seeds - ราคาตามความหายาก
            $costs = [
                'common' => 50,
                'rare' => 100,
                'epic' => 200,
                'legendary' => 500
            ];
            $cost = $costs[$plantType->rarity] ?? 50;
            
            if ($garden->star_seeds < $cost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough Star Seeds to plant this seed',
                    'required' => $cost,
                    'available' => $garden->star_seeds
                ], 400);
            }

            DB::beginTransaction();

            // หัก Star Seeds
            $garden->star_seeds -= $cost;
            $garden->save();

            // ปลูกพืช
            $plant = $garden->plants()->create([
                'user_id' => $user->id,
                'plant_type_id' => $plantType->id,
                'custom_name' => $validated['custom_name'] ?? null,
                'stage' => 0,
                'health' => 100,
                'growth_points' => 0,
                'position' => $validated['position'] ?? null,
                'planted_at' => now(),
                'next_water_at' => now()->addDay(),
                'is_fully_grown' => false
            ]);

            // บันทึกกิจกรรม
            GardenActivity::logActivity(
                $user->id,
                $garden->id,
                'plant',
                'plant',
                $plant->id,
                10, // XP สำหรับการปลูก
                0,
                [
                    'plant_name' => $plant->getDisplayName(),
                    'plant_type' => $plantType->name,
                    'category' => $plantType->category,
                    'cost' => $cost
                ]
            );

            // ให้ XP สำหรับการปลูก
            $garden->addXp(10);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plant successfully planted!',
                'data' => [
                    'plant' => [
                        'id' => $plant->id,
                        'name' => $plant->getDisplayName(),
                        'type' => $plantType->name,
                        'category' => $plantType->category,
                        'stage' => $plant->stage,
                        'health' => $plant->health,
                        'position' => $plant->position,
                        'planted_at' => $plant->planted_at->format('Y-m-d H:i:s')
                    ],
                    'garden' => [
                        'star_seeds' => $garden->star_seeds,
                        'xp' => $garden->xp,
                        'level' => $garden->level
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to plant seed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Water a plant
     */
    public function waterPlant(Request $request, string $userPlantId): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? User::first();
            $plant = UserPlant::where('user_id', $user->id)->find($userPlantId);

            if (!$plant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plant not found'
                ], 404);
            }

            if (!$plant->needsWatering()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plant does not need watering yet',
                    'next_water_at' => $plant->next_water_at?->format('Y-m-d H:i:s')
                ], 400);
            }

            if ($plant->is_fully_grown) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fully grown plants do not need watering'
                ], 400);
            }

            DB::beginTransaction();

            $oldStage = $plant->stage;
            $plant->water();

            // ตรวจสอบว่าเติบโตหรือไม่
            $grewUp = $plant->stage > $oldStage;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plant watered successfully!',
                'data' => [
                    'plant' => [
                        'id' => $plant->id,
                        'health' => $plant->health,
                        'growth_points' => $plant->growth_points,
                        'growth_progress' => $plant->getGrowthProgress(),
                        'stage' => $plant->stage,
                        'stage_name' => $plant->getCurrentStageName(),
                        'needs_watering' => $plant->needsWatering(),
                        'next_water_at' => $plant->next_water_at?->format('Y-m-d H:i:s'),
                        'is_fully_grown' => $plant->is_fully_grown
                    ],
                    'grew_up' => $grewUp,
                    'rewards' => [
                        'xp' => 5,
                        'star_seeds' => 2
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to water plant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Harvest a fully grown plant
     */
    public function harvestPlant(Request $request, string $userPlantId): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? User::first();
            $plant = UserPlant::where('user_id', $user->id)->find($userPlantId);

            if (!$plant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plant not found'
                ], 404);
            }

            if (!$plant->is_fully_grown) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plant is not fully grown yet'
                ], 400);
            }

            if ($plant->harvested_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plant has already been harvested'
                ], 400);
            }

            DB::beginTransaction();

            $bonusXp = $plant->plantType->base_xp_reward;
            $bonusStarSeeds = $plant->plantType->getStarSeedsReward() * 2;

            $plant->harvest();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plant harvested successfully!',
                'data' => [
                    'plant' => [
                        'id' => $plant->id,
                        'name' => $plant->getDisplayName(),
                        'harvested_at' => $plant->harvested_at->format('Y-m-d H:i:s')
                    ],
                    'rewards' => [
                        'xp' => $bonusXp,
                        'star_seeds' => $bonusStarSeeds,
                        'message' => "คุณได้รับ {$bonusXp} XP และ {$bonusStarSeeds} Star Seeds!"
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to harvest plant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Water the entire garden
     */
    public function waterGarden(Request $request): JsonResponse
    {
        try {
            // For testing - use demo user if no auth
            $user = $request->user() ?? User::first();
            $garden = $user->getOrCreateGarden();

            if (!$garden->needsWatering()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garden does not need watering yet',
                    'last_watered_at' => $garden->last_watered_at?->format('Y-m-d H:i:s')
                ], 400);
            }

            DB::beginTransaction();

            $garden->water();

            // รดน้ำพืชทั้งหมดที่ต้องการน้ำ
            $plantsNeedingWater = $garden->plants()->needsWatering()->get();
            $wateredCount = 0;

            foreach ($plantsNeedingWater as $plant) {
                $plant->water();
                $wateredCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Garden watered successfully!',
                'data' => [
                    'garden' => [
                        'last_watered_at' => $garden->last_watered_at?->format('Y-m-d H:i:s'),
                        'xp' => $garden->xp,
                        'level' => $garden->level,
                        'star_seeds' => $garden->star_seeds
                    ],
                    'plants_watered' => $wateredCount,
                    'rewards' => [
                        'xp' => 20 + ($wateredCount * 5),
                        'star_seeds' => 5 + ($wateredCount * 2)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to water garden',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}