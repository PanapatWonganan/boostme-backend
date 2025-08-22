<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGarden;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GardenThemeController extends Controller
{
    /**
     * Get current user ID (for testing, use first user)
     */
    private function getCurrentUserId(): string
    {
        // For testing purposes, use first user like GardenController
        // In production, this should use Auth::id()
        $user = User::first();
        return $user ? $user->id : '0198b246-1b0e-7cd6-8f5e-8a0a5b787402';
    }

    /**
     * Get available garden themes
     */
    public function getAvailableThemes(): JsonResponse
    {
        try {
            $themes = [
                [
                    'id' => 'tropical',
                    'name' => 'สวนเมืองร้อน',
                    'description' => 'สวนสไตล์เมืองร้อนด้วยพืชพื้นเมืองไทย',
                    'preview_image' => '/images/themes/tropical-preview.jpg',
                    'background_color' => '#10B981',
                    'accent_color' => '#F59E0B',
                    'unlock_level' => 1,
                    'price' => 0,
                    'features' => [
                        'พื้นหลังสีเขียวมรกต',
                        'การ์ดพืชสีส้มทอง',
                        'เอฟเฟกต์ใบไม้ร่วง',
                        'เสียงนกร้องเบาๆ'
                    ],
                    'category' => 'nature'
                ],
                [
                    'id' => 'zen',
                    'name' => 'สวนเซน',
                    'description' => 'สวนสงบในสไตล์ญี่ปุ่น เน้นความเรียบง่าย',
                    'preview_image' => '/images/themes/zen-preview.jpg',
                    'background_color' => '#6366F1',
                    'accent_color' => '#8B5CF6',
                    'unlock_level' => 5,
                    'price' => 100,
                    'features' => [
                        'พื้นหลังสีม่วงอ่อน',
                        'การ์ดพืชสีม่วงเข้ม',
                        'เอฟเฟกต์น้ำไหล',
                        'เสียงธรรมชาติแบบเซน'
                    ],
                    'category' => 'mindfulness'
                ],
                [
                    'id' => 'cottage',
                    'name' => 'สวนคอทเทจ',
                    'description' => 'สวนแบบบ้านชนบทอังกฤษ อบอุ่นและน่ารัก',
                    'preview_image' => '/images/themes/cottage-preview.jpg',
                    'background_color' => '#EC4899',
                    'accent_color' => '#F97316',
                    'unlock_level' => 10,
                    'price' => 200,
                    'features' => [
                        'พื้นหลังสีชมพูอ่อน',
                        'การ์ดพืชสีส้มสวย',
                        'เอฟเฟกต์ผีเสื้อบิน',
                        'เสียงลมเบาๆ'
                    ],
                    'category' => 'cozy'
                ],
                [
                    'id' => 'modern',
                    'name' => 'สวนโมเดิร์น',
                    'description' => 'สวนสไตล์โมเดิร์นมินิมอล เน้นความเรียบหรู',
                    'preview_image' => '/images/themes/modern-preview.jpg',
                    'background_color' => '#374151',
                    'accent_color' => '#06B6D4',
                    'unlock_level' => 15,
                    'price' => 300,
                    'features' => [
                        'พื้นหลังสีเทาเข้ม',
                        'การ์ดพืชสีฟ้าสว่าง',
                        'เอฟเฟกต์แสงไฟ LED',
                        'เสียงธรรมชาติอ่อนหวาน'
                    ],
                    'category' => 'modern'
                ],
                [
                    'id' => 'seasonal_spring',
                    'name' => 'ฤดูใบไม้ผลิ',
                    'description' => 'สวนในฤดูใบไม้ผลิ เต็มไปด้วยดอกไม้สีสวย',
                    'preview_image' => '/images/themes/spring-preview.jpg',
                    'background_color' => '#F472B6',
                    'accent_color' => '#A855F7',
                    'unlock_level' => 20,
                    'price' => 500,
                    'features' => [
                        'พื้นหลังสีชมพูสดใส',
                        'การ์ดพืชสีม่วงสวย',
                        'เอฟเฟกต์กลีบดอกไม้ร่วง',
                        'เสียงนกร้องเช้า'
                    ],
                    'category' => 'seasonal',
                    'is_seasonal' => true,
                    'seasonal_period' => 'spring'
                ],
                [
                    'id' => 'premium_gold',
                    'name' => 'สวนทองคำ',
                    'description' => 'สวนพรีเมียมสีทองหรูหรา สำหรับนักสวนระดับสูง',
                    'preview_image' => '/images/themes/gold-preview.jpg',
                    'background_color' => '#F59E0B',
                    'accent_color' => '#FBBF24',
                    'unlock_level' => 25,
                    'price' => 1000,
                    'features' => [
                        'พื้นหลังสีทองเงา',
                        'การ์ดพืชสีทองสวยหรู',
                        'เอฟเฟกต์ดาวระยิบระยับ',
                        'เสียงเพลงคลาสสิค'
                    ],
                    'category' => 'premium',
                    'is_premium' => true
                ]
            ];

            $userId = $this->getCurrentUserId();
            $garden = UserGarden::where('user_id', $userId)->first();
            $userLevel = $garden ? $garden->level : 1;
            $userStarSeeds = $garden ? $garden->star_seeds : 0;

            // Add availability status to each theme
            $themesWithStatus = array_map(function($theme) use ($userLevel, $userStarSeeds, $garden) {
                $canUnlock = $userLevel >= $theme['unlock_level'] && $userStarSeeds >= $theme['price'];
                $isUnlocked = $canUnlock || $theme['price'] == 0; // Free themes are always unlocked
                $isActive = $garden && $garden->theme === $theme['id'];

                return array_merge($theme, [
                    'can_unlock' => $canUnlock,
                    'is_unlocked' => $isUnlocked,
                    'is_active' => $isActive,
                    'unlock_requirements' => [
                        'level_met' => $userLevel >= $theme['unlock_level'],
                        'price_affordable' => $userStarSeeds >= $theme['price'],
                        'required_level' => $theme['unlock_level'],
                        'required_star_seeds' => $theme['price']
                    ]
                ]);
            }, $themes);

            return response()->json([
                'success' => true,
                'data' => [
                    'themes' => $themesWithStatus,
                    'user_info' => [
                        'level' => $userLevel,
                        'star_seeds' => $userStarSeeds,
                        'current_theme' => $garden ? $garden->theme : 'tropical'
                    ],
                    'stats' => [
                        'total_themes' => count($themes),
                        'unlocked_themes' => count(array_filter($themesWithStatus, fn($t) => $t['is_unlocked'])),
                        'premium_themes' => count(array_filter($themes, fn($t) => isset($t['is_premium']))),
                        'seasonal_themes' => count(array_filter($themes, fn($t) => isset($t['is_seasonal'])))
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลธีมได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply theme to user's garden
     */
    public function applyTheme(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'theme_id' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ข้อมูลไม่ถูกต้อง',
                    'errors' => $validator->errors()
                ], 400);
            }

            $userId = $this->getCurrentUserId();
            $garden = UserGarden::where('user_id', $userId)->first();

            if (!$garden) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบสวนของผู้ใช้'
                ], 404);
            }

            $themeId = $request->theme_id;

            // Get theme info (in a real app, this would come from a database)
            $availableThemes = $this->getThemeDefinitions();
            $selectedTheme = collect($availableThemes)->firstWhere('id', $themeId);

            if (!$selectedTheme) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบธีมที่เลือก'
                ], 404);
            }

            // Check if user can use this theme
            if ($garden->level < $selectedTheme['unlock_level']) {
                return response()->json([
                    'success' => false,
                    'message' => "ต้องอยู่ในระดับ {$selectedTheme['unlock_level']} ขึ้นไป"
                ], 400);
            }

            if ($garden->star_seeds < $selectedTheme['price']) {
                return response()->json([
                    'success' => false,
                    'message' => "ต้องมี Star Seeds อย่างน้อย {$selectedTheme['price']} เหรียญ"
                ], 400);
            }

            // Check if theme is already active
            if ($garden->theme === $themeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ธีมนี้กำลังใช้งานอยู่แล้ว'
                ], 400);
            }

            // Deduct Star Seeds if it's a paid theme (unless already owned)
            $previouslyOwned = false; // In a real app, track owned themes
            if ($selectedTheme['price'] > 0 && !$previouslyOwned) {
                $garden->star_seeds -= $selectedTheme['price'];
            }

            // Apply the theme
            $garden->theme = $themeId;
            $garden->save();

            // Log this activity
            \App\Models\GardenActivity::create([
                'user_id' => $userId,
                'garden_id' => $garden->id,
                'activity_type' => 'theme_changed',
                'target_type' => 'theme',
                'target_id' => $themeId,
                'xp_earned' => 10, // Small XP reward for customization
                'description' => "เปลี่ยนธีมสวนเป็น {$selectedTheme['name']}",
                'metadata' => json_encode([
                    'theme_name' => $selectedTheme['name'],
                    'price_paid' => $previouslyOwned ? 0 : $selectedTheme['price']
                ])
            ]);

            // Give small XP reward for customization
            $garden->xp += 10;
            $garden->save();

            return response()->json([
                'success' => true,
                'message' => "เปลี่ยนธีมเป็น {$selectedTheme['name']} เรียบร้อยแล้ว!",
                'data' => [
                    'theme' => $selectedTheme,
                    'garden' => [
                        'id' => $garden->id,
                        'theme' => $garden->theme,
                        'star_seeds' => $garden->star_seeds,
                        'xp' => $garden->xp,
                        'level' => $garden->level
                    ],
                    'rewards' => [
                        'xp_gained' => 10,
                        'star_seeds_spent' => $previouslyOwned ? 0 : $selectedTheme['price']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเปลี่ยนธีมได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's current theme details
     */
    public function getCurrentTheme(): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $garden = UserGarden::where('user_id', $userId)->first();

            if (!$garden) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบสวนของผู้ใช้'
                ], 404);
            }

            $currentThemeId = $garden->theme ?? 'tropical';
            $availableThemes = $this->getThemeDefinitions();
            $currentTheme = collect($availableThemes)->firstWhere('id', $currentThemeId);

            if (!$currentTheme) {
                // Fallback to default theme
                $currentTheme = collect($availableThemes)->firstWhere('id', 'tropical');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'current_theme' => $currentTheme,
                    'garden_info' => [
                        'level' => $garden->level,
                        'xp' => $garden->xp,
                        'star_seeds' => $garden->star_seeds,
                        'theme' => $garden->theme
                    ],
                    'customization_stats' => [
                        'theme_changes_today' => 0, // TODO: Track this
                        'total_spent_on_themes' => 0, // TODO: Track this
                        'favorite_theme_category' => 'nature' // TODO: Calculate this
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลธีมปัจจุบันได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get theme definitions (in a real app, this would be in database)
     */
    private function getThemeDefinitions(): array
    {
        return [
            [
                'id' => 'tropical',
                'name' => 'สวนเมืองร้อน',
                'description' => 'สวนสไตล์เมืองร้อนด้วยพืชพื้นเมืองไทย',
                'unlock_level' => 1,
                'price' => 0
            ],
            [
                'id' => 'zen',
                'name' => 'สวนเซน',
                'description' => 'สวนสงบในสไตล์ญี่ปุ่น เน้นความเรียบง่าย',
                'unlock_level' => 5,
                'price' => 100
            ],
            [
                'id' => 'cottage',
                'name' => 'สวนคอทเทจ',
                'description' => 'สวนแบบบ้านชนบทอังกฤษ อบอุ่นและน่ารัก',
                'unlock_level' => 10,
                'price' => 200
            ],
            [
                'id' => 'modern',
                'name' => 'สวนโมเดิร์น',
                'description' => 'สวนสไตล์โมเดิร์นมินิมอล เน้นความเรียบหรู',
                'unlock_level' => 15,
                'price' => 300
            ],
            [
                'id' => 'seasonal_spring',
                'name' => 'ฤดูใบไม้ผลิ',
                'description' => 'สวนในฤดูใบไม้ผลิ เต็มไปด้วยดอกไม้สีสวย',
                'unlock_level' => 20,
                'price' => 500
            ],
            [
                'id' => 'premium_gold',
                'name' => 'สวนทองคำ',
                'description' => 'สวนพรีเมียมสีทองหรูหรา สำหรับนักสวนระดับสูง',
                'unlock_level' => 25,
                'price' => 1000
            ]
        ];
    }
}