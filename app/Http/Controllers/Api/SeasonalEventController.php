<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGarden;
use App\Models\User;
use App\Models\GardenActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SeasonalEventController extends Controller
{
    /**
     * Get current user ID (for testing, use first user)
     */
    private function getCurrentUserId(): string
    {
        $user = User::first();
        return $user ? $user->id : '0198b246-1b0e-7cd6-8f5e-8a0a5b787402';
    }

    /**
     * Get current seasonal events
     */
    public function getCurrentEvents(): JsonResponse
    {
        try {
            $currentDate = now();
            $events = $this->generateSeasonalEvents($currentDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'current_events' => $events['active'],
                    'upcoming_events' => $events['upcoming'],
                    'weather_info' => $this->getCurrentWeather(),
                    'seasonal_boosts' => $this->getSeasonalBoosts($currentDate),
                    'thai_calendar' => $this->getThaiCalendarInfo($currentDate)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลเหตุการณ์ตามฤดูกาลได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Participate in seasonal event
     */
    public function participateEvent(Request $request, string $eventId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'activity_type' => 'required|string|in:plant_special,water_blessing,make_offering,join_ceremony',
                'contribution_amount' => 'sometimes|integer|min:1|max:100'
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

            // Get event details
            $event = $this->getEventById($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบเหตุการณ์ที่ระบุ'
                ], 404);
            }

            // Process participation
            $participation = $this->processEventParticipation(
                $garden, 
                $event, 
                $request->activity_type,
                $request->contribution_amount ?? 1
            );

            return response()->json([
                'success' => true,
                'message' => $participation['message'],
                'data' => $participation['result']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเข้าร่วมเหตุการณ์ได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get weather system status
     */
    public function getWeatherStatus(): JsonResponse
    {
        try {
            $weather = $this->getCurrentWeather();
            $weatherEffects = $this->getWeatherEffects($weather);

            return response()->json([
                'success' => true,
                'data' => [
                    'current_weather' => $weather,
                    'effects' => $weatherEffects,
                    'forecast' => $this->getWeatherForecast(),
                    'garden_recommendations' => $this->getWeatherRecommendations($weather)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลสภาพอากาศได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate seasonal plant
     */
    public function activateSeasonalPlant(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plant_type' => 'required|string|in:songkran_lotus,loy_krathong_banana,mothers_day_jasmine,christmas_pine',
                'position' => 'sometimes|array'
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

            $plantType = $request->plant_type;
            $seasonalPlant = $this->createSeasonalPlant($garden, $plantType);

            return response()->json([
                'success' => true,
                'message' => "ปลูก{$seasonalPlant['name']}สำเร็จ! พืชพิเศษตามเทศกาล",
                'data' => $seasonalPlant
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถปลูกพืชตามฤดูกาลได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate seasonal events based on current date
     */
    private function generateSeasonalEvents(Carbon $date): array
    {
        $events = [
            'active' => [],
            'upcoming' => []
        ];

        // Thai New Year (Songkran) - April 13-15
        if ($date->month == 4 && $date->day >= 13 && $date->day <= 15) {
            $events['active'][] = [
                'id' => 'songkran-2024',
                'name' => 'เทศกาลสงกรานต์',
                'description' => 'เทศกาลแห่งน้ำและการเริ่มต้นใหม่',
                'type' => 'thai_festival',
                'start_date' => '2024-04-13',
                'end_date' => '2024-04-15',
                'activities' => [
                    [
                        'name' => 'พิธีรดน้ำพระ',
                        'description' => 'รดน้ำพืชศักดิ์สิทธิ์เพื่อความเป็นสิริมงคล',
                        'reward_xp' => 100,
                        'reward_star_seeds' => 50,
                        'special_item' => 'น้ำมนต์สงกรานต์'
                    ],
                    [
                        'name' => 'ปลูกบัวสงกรานต์',
                        'description' => 'ปลูกดอกบัวพิเศษที่บานได้เพียงช่วงสงกรานต์',
                        'reward_xp' => 200,
                        'reward_star_seeds' => 100,
                        'special_plant' => 'บัวสงกรานต์'
                    ]
                ],
                'community_goal' => [
                    'description' => 'รดน้ำพืชร่วมกัน 10,000 ครั้ง',
                    'progress' => 7856,
                    'target' => 10000,
                    'reward' => 'ธีมสวนสงกรานต์พิเศษ'
                ],
                'special_effects' => [
                    'water_efficiency' => '+50%',
                    'lotus_growth_speed' => '+100%',
                    'blessing_effects' => 'active'
                ]
            ];
        }

        // Loy Krathong - November (full moon)
        if ($date->month == 11 && $date->day >= 15 && $date->day <= 20) {
            $events['active'][] = [
                'id' => 'loy-krathong-2024',
                'name' => 'เทศกาลลอยกระทง',
                'description' => 'เทศกาลแห่งการลอยกระทงและการขอขมา',
                'type' => 'thai_festival',
                'start_date' => '2024-11-15',
                'end_date' => '2024-11-17',
                'activities' => [
                    [
                        'name' => 'ลอยกระทงใบตอง',
                        'description' => 'ทำกระทงจากใบตองในสวนเพื่อลอยข้อมกราว',
                        'reward_xp' => 150,
                        'reward_star_seeds' => 75,
                        'special_item' => 'กระทงศักดิ์สิทธิ์'
                    ],
                    [
                        'name' => 'เพาะบัวเต็มดวง',
                        'description' => 'ปลูกดอกบัวที่บานในคืนพระจันทร์เต็มดวง',
                        'reward_xp' => 250,
                        'reward_star_seeds' => 125,
                        'special_plant' => 'บัวเต็มดวง'
                    ]
                ],
                'community_goal' => [
                    'description' => 'ลอยกระทงร่วมกัน 5,000 ใบ',
                    'progress' => 3421,
                    'target' => 5000,
                    'reward' => 'บัวแสงจันทร์ (พืชหายาก)'
                ]
            ];
        }

        // Mother's Day - August 12
        if ($date->month == 8 && $date->day == 12) {
            $events['active'][] = [
                'id' => 'mothers-day-2024',
                'name' => 'วันแม่แห่งชาติ',
                'description' => 'วันสำคัญเพื่อแสดงความกตัญญูต่อมารดา',
                'type' => 'national_day',
                'start_date' => '2024-08-12',
                'end_date' => '2024-08-12',
                'activities' => [
                    [
                        'name' => 'ปลูกมะลิของแม่',
                        'description' => 'ปลูกดอกมะลิสีขาวเพื่อแม่',
                        'reward_xp' => 300,
                        'reward_star_seeds' => 150,
                        'special_plant' => 'มะลิแม่'
                    ]
                ]
            ];
        }

        // Check for upcoming events (next 30 days)
        $nextMonth = $date->copy()->addMonth();
        if ($nextMonth->month == 4) {
            $events['upcoming'][] = [
                'id' => 'songkran-next',
                'name' => 'เทศกาลสงกรานต์',
                'start_date' => $nextMonth->setDay(13)->toDateString(),
                'days_until' => $date->diffInDays($nextMonth->setDay(13))
            ];
        }

        return $events;
    }

    /**
     * Get current weather simulation
     */
    private function getCurrentWeather(): array
    {
        $weatherTypes = ['sunny', 'cloudy', 'rainy', 'storm', 'misty'];
        $currentHour = now()->hour;
        
        // Simulate weather based on time and season
        $weather = [
            'type' => $weatherTypes[array_rand($weatherTypes)],
            'temperature' => rand(25, 35), // Celsius
            'humidity' => rand(60, 85), // Percentage
            'wind_speed' => rand(5, 15), // km/h
            'description' => '',
            'effects' => []
        ];

        switch ($weather['type']) {
            case 'rainy':
                $weather['description'] = 'ฝนตก - พืชได้รับน้ำธรรมชาติ';
                $weather['effects'] = ['วอเตอร์ระยะเวลาเพิ่มขึ้น 2 เท่า', 'พืชเติบโตเร็วขึ้น 25%'];
                break;
            case 'sunny':
                $weather['description'] = 'แดดจัด - ต้องรดน้ำบ่อยขึ้น';
                $weather['effects'] = ['พืชต้องการน้ำเพิ่ม 50%', 'โฟโตซินเธซิสเพิ่มขึ้น'];
                break;
            case 'storm':
                $weather['description'] = 'พายุ - พืชต้องการการดูแลพิเศษ';
                $weather['effects'] = ['สุขภาพพืชลดลง 10%', 'แต่ได้ XP เพิ่ม 50%'];
                break;
            case 'misty':
                $weather['description'] = 'หมอก - บรรยากาศเหมาะสำหรับสมาธิ';
                $weather['effects'] = ['พืชประเภท Mental เติบโตเร็ว 2 เท่า'];
                break;
            default:
                $weather['description'] = 'อากาศใส - เหมาะสำหรับทำสวน';
                $weather['effects'] = ['ทุกกิจกรรมปกติ'];
        }

        return $weather;
    }

    /**
     * Get weather effects on garden
     */
    private function getWeatherEffects(array $weather): array
    {
        $effects = [
            'water_consumption' => 1.0,
            'growth_speed' => 1.0,
            'health_change' => 0,
            'xp_modifier' => 1.0,
            'special_bonuses' => []
        ];

        switch ($weather['type']) {
            case 'rainy':
                $effects['water_consumption'] = 0.5; // Less water needed
                $effects['growth_speed'] = 1.25; // 25% faster growth
                $effects['special_bonuses'][] = 'ฝนธรรมชาติชดเชยการรดน้ำ';
                break;

            case 'sunny':
                $effects['water_consumption'] = 1.5; // More water needed
                $effects['growth_speed'] = 1.1; // 10% faster growth (photosynthesis)
                $effects['special_bonuses'][] = 'โฟโตซินเธซิสเพิ่มขึ้น';
                break;

            case 'storm':
                $effects['health_change'] = -5; // Health decreases
                $effects['xp_modifier'] = 1.5; // 50% more XP (challenging conditions)
                $effects['special_bonuses'][] = 'สภาพอากาศท้าทาย - XP เพิ่ม';
                break;

            case 'misty':
                $effects['xp_modifier'] = 1.25; // Mental plants benefit
                $effects['special_bonuses'][] = 'บรรยากาศเงียบสงบ - เหมาะกับพืชจิตใจ';
                break;
        }

        return $effects;
    }

    /**
     * Get weather forecast for next 7 days
     */
    private function getWeatherForecast(): array
    {
        $forecast = [];
        $weatherTypes = ['sunny', 'cloudy', 'rainy', 'misty'];

        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i);
            $forecast[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->locale('th')->dayName,
                'weather' => $weatherTypes[array_rand($weatherTypes)],
                'temperature_high' => rand(28, 35),
                'temperature_low' => rand(22, 27),
                'rain_chance' => rand(0, 80)
            ];
        }

        return $forecast;
    }

    /**
     * Get seasonal boosts based on current date
     */
    private function getSeasonalBoosts(Carbon $date): array
    {
        $boosts = [];

        // Rainy season (May-October)
        if ($date->month >= 5 && $date->month <= 10) {
            $boosts[] = [
                'name' => 'ฤดูฝน',
                'description' => 'พืชเติบโตเร็วขึ้นและต้องการน้ำน้อยลง',
                'effects' => [
                    'growth_speed' => '+25%',
                    'water_efficiency' => '+50%'
                ]
            ];
        }

        // Cool season (November-February)
        if ($date->month >= 11 || $date->month <= 2) {
            $boosts[] = [
                'name' => 'ฤดูหนาว',
                'description' => 'อากาศเย็นเหมาะสำหรับพืชประเภทจิตใจ',
                'effects' => [
                    'mental_plant_bonus' => '+100%',
                    'health_preservation' => '+30%'
                ]
            ];
        }

        // Hot season (March-May)
        if ($date->month >= 3 && $date->month <= 5) {
            $boosts[] = [
                'name' => 'ฤดูร้อน',
                'description' => 'แสงแดดจัดเหมาะสำหรับพืชประเภทออกกำลังกาย',
                'effects' => [
                    'fitness_plant_bonus' => '+75%',
                    'photosynthesis_boost' => '+50%'
                ]
            ];
        }

        return $boosts;
    }

    /**
     * Get Thai calendar information
     */
    private function getThaiCalendarInfo(Carbon $date): array
    {
        return [
            'buddhist_year' => $date->year + 543,
            'thai_month' => $this->getThaiMonthName($date->month),
            'lunar_day' => rand(1, 15), // Simplified lunar calendar
            'auspicious_time' => $this->getAuspiciousTime($date),
            'lucky_plants' => $this->getLuckyPlants($date)
        ];
    }

    /**
     * Helper methods
     */
    private function getThaiMonthName(int $month): string
    {
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
        ];
        return $thaiMonths[$month];
    }

    private function getAuspiciousTime(Carbon $date): string
    {
        $times = ['เช้า (06:00-08:00)', 'สาย (09:00-11:00)', 'บ่าย (14:00-16:00)', 'เย็น (17:00-19:00)'];
        return $times[array_rand($times)];
    }

    private function getLuckyPlants(Carbon $date): array
    {
        $plants = ['กุหลาบ', 'มะลิ', 'ลาเวนเดอร์', 'ต้นโอ๊ก'];
        return array_slice($plants, 0, rand(2, 3));
    }

    private function getEventById(string $eventId): ?array
    {
        $events = $this->generateSeasonalEvents(now());
        return collect($events['active'])->firstWhere('id', $eventId);
    }

    private function processEventParticipation(UserGarden $garden, array $event, string $activityType, int $contribution): array
    {
        // Simulate event participation
        $xpGained = 100 * $contribution;
        $starSeedsGained = 50 * $contribution;

        $garden->xp += $xpGained;
        $garden->star_seeds += $starSeedsGained;
        $garden->save();

        return [
            'message' => "เข้าร่วม {$event['name']} สำเร็จ!",
            'result' => [
                'xp_gained' => $xpGained,
                'star_seeds_gained' => $starSeedsGained,
                'participation_count' => $contribution,
                'event_progress_contribution' => $contribution
            ]
        ];
    }

    private function createSeasonalPlant(UserGarden $garden, string $plantType): array
    {
        $seasonalPlants = [
            'songkran_lotus' => [
                'name' => 'บัวสงกรานต์',
                'description' => 'ดอกบัวศักดิ์สิทธิ์ที่บานในช่วงเทศกาลสงกรานต์',
                'special_abilities' => ['water_blessing', 'purification']
            ],
            'loy_krathong_banana' => [
                'name' => 'ต้นกล้วยกระทง',
                'description' => 'ต้นกล้วยที่ให้ใบสำหรับทำกระทง',
                'special_abilities' => ['krathong_leaves', 'moonlight_glow']
            ],
            'mothers_day_jasmine' => [
                'name' => 'มะลิแม่',
                'description' => 'ดอกมะลิสีขาวบริสุทธิ์เพื่อแม่',
                'special_abilities' => ['love_essence', 'gratitude_aura']
            ]
        ];

        return $seasonalPlants[$plantType] ?? $seasonalPlants['songkran_lotus'];
    }

    private function getWeatherRecommendations(array $weather): array
    {
        $recommendations = [];

        switch ($weather['type']) {
            case 'rainy':
                $recommendations[] = 'ช่วงนี้เหมาะสำหรับปลูกพืชใหม่';
                $recommendations[] = 'ไม่ต้องรดน้ำบ่อยเหมือนปกติ';
                break;
            case 'sunny':
                $recommendations[] = 'ควรรดน้ำพืชเช้า-เย็น';
                $recommendations[] = 'เวลาดีสำหรับเก็บเกี่ยวผลไม้';
                break;
            case 'storm':
                $recommendations[] = 'ตรวจสอบสุขภาพพืชบ่อยขึ้น';
                $recommendations[] = 'ช่วงนี้ได้ XP เพิ่มจากการดูแล';
                break;
            default:
                $recommendations[] = 'อากาศดี เหมาะสำหรับทำสวน';
        }

        return $recommendations;
    }
}