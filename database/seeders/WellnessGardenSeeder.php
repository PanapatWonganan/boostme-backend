<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlantType;
use App\Models\Achievement;
use App\Models\DailyChallenge;
use Carbon\Carbon;

class WellnessGardenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedPlantTypes();
        $this->seedAchievements();
        $this->seedDailyChallenges();
    }

    private function seedPlantTypes(): void
    {
        $plantTypes = [
            // Fitness Plants 🌸
            [
                'name' => 'กุหลาบ',
                'category' => 'fitness',
                'rarity' => 'common',
                'description' => 'สัญลักษณ์ของความแข็งแกร่งและการออกกำลังกายหัวใจ เหมาะสำหรับผู้ที่รักการ Cardio',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ดกุหลาบ', 'duration_hours' => 12],
                    1 => ['name' => 'ใบอ่อน', 'duration_hours' => 48],
                    2 => ['name' => 'ต้นอ่อน', 'duration_hours' => 168],
                    3 => ['name' => 'กิ่งแรก', 'duration_hours' => 336],
                    4 => ['name' => 'ดอกบาน', 'duration_hours' => 720]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 6,
                    'fertilizer_needed' => true,
                    'special_care' => 'ต้องการการออกกำลังกายสม่ำเสมอ'
                ],
                'base_xp_reward' => 50,
                'unlock_level' => 1,
                'icon_path' => '🌹'
            ],
            [
                'name' => 'ทานตะวัน',
                'category' => 'fitness',
                'rarity' => 'common',
                'description' => 'สร้างพลังงานและความแข็งแกร่งเหมือนดวงอาทิตย์ เสริมพลังกาย',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ดทานตะวัน', 'duration_hours' => 8],
                    1 => ['name' => 'หน่อแรก', 'duration_hours' => 24],
                    2 => ['name' => 'ต้นอ่อน', 'duration_hours' => 120],
                    3 => ['name' => 'โตเต็มที่', 'duration_hours' => 240],
                    4 => ['name' => 'ดอกบาน', 'duration_hours' => 480]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 8,
                    'fertilizer_needed' => false,
                    'special_care' => 'ชอบแสงแดดเยอะ'
                ],
                'base_xp_reward' => 60,
                'unlock_level' => 2,
                'icon_path' => '🌻'
            ],
            [
                'name' => 'ไผ่',
                'category' => 'fitness',
                'rarity' => 'rare',
                'description' => 'สัญลักษณ์ของความยืดหยุ่นและสมดุล เสริมความแข็งแกร่งภายใน',
                'growth_stages' => [
                    0 => ['name' => 'หน่อไผ่', 'duration_hours' => 6],
                    1 => ['name' => 'ต้นอ่อน', 'duration_hours' => 24],
                    2 => ['name' => 'เจริญเติบโต', 'duration_hours' => 72],
                    3 => ['name' => 'แกนแข็ง', 'duration_hours' => 168],
                    4 => ['name' => 'ไผ่โต', 'duration_hours' => 360]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 4,
                    'fertilizer_needed' => true,
                    'special_care' => 'ต้องการการฝึกยืดหยุ่น'
                ],
                'base_xp_reward' => 80,
                'unlock_level' => 5,
                'icon_path' => '🎋'
            ],

            // Nutrition Plants 🍎
            [
                'name' => 'ต้นแอปเปิ้ล',
                'category' => 'nutrition',
                'rarity' => 'common',
                'description' => 'ผลไม้แห่งสุขภาพที่ให้สารอาหารครบครัน บำรุงร่างกาย',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ดแอปเปิ้ล', 'duration_hours' => 24],
                    1 => ['name' => 'ใบแรก', 'duration_hours' => 72],
                    2 => ['name' => 'ต้นเล็ก', 'duration_hours' => 240],
                    3 => ['name' => 'ต้นโต', 'duration_hours' => 720],
                    4 => ['name' => 'ติดผล', 'duration_hours' => 1440]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 6,
                    'fertilizer_needed' => true,
                    'special_care' => 'ต้องการอาหารที่มีคุณภาพ'
                ],
                'base_xp_reward' => 70,
                'unlock_level' => 1,
                'icon_path' => '🍎'
            ],
            [
                'name' => 'สวนสมุนไพร',
                'category' => 'nutrition',
                'rarity' => 'rare',
                'description' => 'รวมสมุนไพรไทยเพื่อสุขภาพ เสริมสร้างภูมิคุ้มกัน',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ดสมุนไพร', 'duration_hours' => 12],
                    1 => ['name' => 'หน่อเล็ก', 'duration_hours' => 36],
                    2 => ['name' => 'ใบเริ่มออก', 'duration_hours' => 120],
                    3 => ['name' => 'โตเต็มที่', 'duration_hours' => 480],
                    4 => ['name' => 'สวนครบ', 'duration_hours' => 960]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 5,
                    'fertilizer_needed' => true,
                    'special_care' => 'ความรู้ด้านสมุนไพร'
                ],
                'base_xp_reward' => 90,
                'unlock_level' => 7,
                'icon_path' => '🌿'
            ],

            // Mental Health Plants 🧘
            [
                'name' => 'ลาเวนเดอร์',
                'category' => 'mental',
                'rarity' => 'common',
                'description' => 'ดอกไม้แห่งความสงบ ช่วยลดความเครียดและผ่อนคลาย',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ดลาเวนเดอร์', 'duration_hours' => 18],
                    1 => ['name' => 'หน่อใหม่', 'duration_hours' => 48],
                    2 => ['name' => 'ใบเจริญ', 'duration_hours' => 144],
                    3 => ['name' => 'ก่อนออกดอก', 'duration_hours' => 360],
                    4 => ['name' => 'ดอกม่วง', 'duration_hours' => 720]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 6,
                    'fertilizer_needed' => false,
                    'special_care' => 'การฝึกสมาธิ'
                ],
                'base_xp_reward' => 55,
                'unlock_level' => 1,
                'icon_path' => '💜'
            ],
            [
                'name' => 'มะลิ',
                'category' => 'mental',
                'rarity' => 'rare',
                'description' => 'ดอกไม้แห่งความบริสุทธิ์ ช่วยทำจิตใจให้สงบและใส',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ดมะลิ', 'duration_hours' => 24],
                    1 => ['name' => 'ใบอ่อน', 'duration_hours' => 72],
                    2 => ['name' => 'ต้นเล็ก', 'duration_hours' => 216],
                    3 => ['name' => 'ก่อนบาน', 'duration_hours' => 504],
                    4 => ['name' => 'ดอกบาน', 'duration_hours' => 1080]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 4,
                    'fertilizer_needed' => true,
                    'special_care' => 'บรรยากาศสงบ'
                ],
                'base_xp_reward' => 85,
                'unlock_level' => 6,
                'icon_path' => '🤍'
            ],

            // Learning Plants 📚
            [
                'name' => 'ต้นโอ๊ก',
                'category' => 'learning',
                'rarity' => 'epic',
                'description' => 'ต้นไม้แห่งปัญญา สัญลักษณ์ของความรู้และการเรียนรู้',
                'growth_stages' => [
                    0 => ['name' => 'โอ๊กเมล็ด', 'duration_hours' => 48],
                    1 => ['name' => 'หน่อแรก', 'duration_hours' => 168],
                    2 => ['name' => 'ต้นเล็ก', 'duration_hours' => 720],
                    3 => ['name' => 'ต้นใหญ่', 'duration_hours' => 2160],
                    4 => ['name' => 'โอ๊กใหญ่', 'duration_hours' => 4320]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 8,
                    'fertilizer_needed' => true,
                    'special_care' => 'การเรียนรู้อย่างสม่ำเสมอ'
                ],
                'base_xp_reward' => 120,
                'unlock_level' => 10,
                'icon_path' => '🌳'
            ],
            [
                'name' => 'ซากุระ',
                'category' => 'learning',
                'rarity' => 'legendary',
                'description' => 'ดอกไม้แห่งการเริ่มต้นใหม่ สื่อถึงการพัฒนาตนเองอย่างต่อเนื่อง',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ดซากุระ', 'duration_hours' => 72],
                    1 => ['name' => 'หน่ออ่อน', 'duration_hours' => 240],
                    2 => ['name' => 'ต้นเล็ก', 'duration_hours' => 1080],
                    3 => ['name' => 'ก่อนบาน', 'duration_hours' => 2880],
                    4 => ['name' => 'ดอกซากุระ', 'duration_hours' => 7200]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 6,
                    'fertilizer_needed' => true,
                    'special_care' => 'ความอดทนและมุ่งมั่น'
                ],
                'base_xp_reward' => 200,
                'unlock_level' => 20,
                'icon_path' => '🌸'
            ]
        ];

        foreach ($plantTypes as $plantData) {
            PlantType::create($plantData);
        }

        $this->command->info('✅ Plant types seeded successfully!');
    }

    private function seedAchievements(): void
    {
        $achievements = [
            // Learning Achievements
            [
                'name' => 'นักปลูกมือใหม่',
                'category' => 'learning',
                'description' => 'ปลูกพืชแรกในสวนของคุณ',
                'badge_icon' => '🌱',
                'rarity' => 'common',
                'criteria' => ['type' => 'plant_grow', 'count' => 1],
                'xp_reward' => 100,
                'star_seeds_reward' => 50,
                'sort_order' => 1
            ],
            [
                'name' => 'นักเรียนขยัน',
                'category' => 'learning',
                'description' => 'เรียนคอร์สจบ 1 คอร์ส',
                'badge_icon' => '📚',
                'rarity' => 'common',
                'criteria' => ['type' => 'course_complete', 'count' => 1],
                'xp_reward' => 200,
                'star_seeds_reward' => 100,
                'sort_order' => 2
            ],
            [
                'name' => 'ปราชญ์แห่งสุขภาพ',
                'category' => 'learning',
                'description' => 'เรียนคอร์สจบครบ 5 คอร์ส',
                'badge_icon' => '🎓',
                'rarity' => 'rare',
                'criteria' => ['type' => 'course_complete', 'count' => 5],
                'xp_reward' => 500,
                'star_seeds_reward' => 300,
                'sort_order' => 10
            ],

            // Fitness Achievements
            [
                'name' => 'นักสู้ยามเช้า',
                'category' => 'fitness',
                'description' => 'ออกกำลังกายเช้าตรู่ติดต่อกัน 7 วัน',
                'badge_icon' => '🌅',
                'rarity' => 'common',
                'criteria' => ['type' => 'consecutive_days', 'days' => 7, 'activity' => 'exercise'],
                'xp_reward' => 300,
                'star_seeds_reward' => 150,
                'sort_order' => 3
            ],
            [
                'name' => 'มาราธอนเนอร์',
                'category' => 'fitness',
                'description' => 'ออกกำลังกายสะสม 100 ชั่วโมง',
                'badge_icon' => '🏃‍♀️',
                'rarity' => 'epic',
                'criteria' => ['type' => 'activity_hours', 'hours' => 100, 'activity' => 'exercise'],
                'xp_reward' => 1000,
                'star_seeds_reward' => 500,
                'sort_order' => 15
            ],

            // Mental Health Achievements
            [
                'name' => 'จิตสงบ',
                'category' => 'mental',
                'description' => 'ฝึกสมาธิครบ 30 วัน',
                'badge_icon' => '🧘‍♀️',
                'rarity' => 'common',
                'criteria' => ['type' => 'consecutive_days', 'days' => 30, 'activity' => 'meditation'],
                'xp_reward' => 400,
                'star_seeds_reward' => 200,
                'sort_order' => 4
            ],
            [
                'name' => 'ชีวิตสมดุล',
                'category' => 'mental',
                'description' => 'ทำกิจกรรมผ่อนคลายทุกประเภท',
                'badge_icon' => '⚖️',
                'rarity' => 'rare',
                'criteria' => ['type' => 'activity_variety', 'activities' => ['meditation', 'yoga', 'relaxation']],
                'xp_reward' => 600,
                'star_seeds_reward' => 350,
                'sort_order' => 12
            ],

            // Social Achievements
            [
                'name' => 'เพื่อนที่ดี',
                'category' => 'social',
                'description' => 'ช่วยเหลือเพื่อนในสวนครบ 10 ครั้ง',
                'badge_icon' => '🤝',
                'rarity' => 'common',
                'criteria' => ['type' => 'help_friends', 'count' => 10],
                'xp_reward' => 250,
                'star_seeds_reward' => 125,
                'sort_order' => 5
            ],
            [
                'name' => 'ผู้นำชุมชน',
                'category' => 'social',
                'description' => 'มีเพื่อนในสวนมากกว่า 20 คน',
                'badge_icon' => '👑',
                'rarity' => 'epic',
                'criteria' => ['type' => 'friend_count', 'count' => 20],
                'xp_reward' => 800,
                'star_seeds_reward' => 400,
                'sort_order' => 18
            ],

            // Special Achievements
            [
                'name' => 'นักสวนระดับ 5',
                'category' => 'special',
                'description' => 'เลื่อนระดับสวนถึง Level 5',
                'badge_icon' => '🏆',
                'rarity' => 'rare',
                'criteria' => ['type' => 'level_reach', 'level' => 5],
                'xp_reward' => 500,
                'star_seeds_reward' => 250,
                'sort_order' => 20
            ],
            [
                'name' => 'มาสเตอร์การ์เดนเนอร์',
                'category' => 'special',
                'description' => 'เลื่อนระดับสวนถึง Level 20',
                'badge_icon' => '🌟',
                'rarity' => 'legendary',
                'criteria' => ['type' => 'level_reach', 'level' => 20],
                'xp_reward' => 2000,
                'star_seeds_reward' => 1000,
                'sort_order' => 50
            ]
        ];

        foreach ($achievements as $achievementData) {
            Achievement::create($achievementData);
        }

        $this->command->info('✅ Achievements seeded successfully!');
    }

    private function seedDailyChallenges(): void
    {
        // สร้าง challenge สำหรับ 7 วันถัดไป
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::today()->addDays($i);
            DailyChallenge::createDailyChallenge($date);
        }

        $this->command->info('✅ Daily challenges seeded successfully!');
    }
}