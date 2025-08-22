<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlantType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'category',
        'rarity',
        'growth_stages',
        'care_requirements',
        'icon_path',
        'description',
        'base_xp_reward',
        'unlock_level',
        'is_active'
    ];

    protected $casts = [
        'growth_stages' => 'array',
        'care_requirements' => 'array',
        'is_active' => 'boolean',
    ];

    // ความสัมพันธ์กับพืชของผู้ใช้
    public function userPlants(): HasMany
    {
        return $this->hasMany(UserPlant::class, 'plant_type_id');
    }

    // พืชที่ผู้ใช้ปลูกแล้วและเติบโตเต็มที่
    public function completedPlants(): HasMany
    {
        return $this->hasMany(UserPlant::class, 'plant_type_id')
                    ->where('is_fully_grown', true);
    }

    // Scope สำหรับกรองตาม category
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Scope สำหรับกรองตาม rarity
    public function scopeByRarity($query, string $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    // Scope สำหรับพืชที่ active
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope สำหรับพืชที่ผู้ใช้สามารถปลูกได้ (ตาม level)
    public function scopeUnlockedForLevel($query, int $level)
    {
        return $query->where('unlock_level', '<=', $level)
                     ->where('is_active', true);
    }

    // ตรวจสอบว่าผู้ใช้สามารถปลูกพืชนี้ได้หรือไม่
    public function canBeUnlockedByLevel(int $userLevel): bool
    {
        return $this->unlock_level <= $userLevel && $this->is_active;
    }

    // คำนวณเวลาที่ใช้ในการเติบโตแต่ละ stage
    public function getGrowthTimeForStage(int $stage): int
    {
        $growthStages = $this->growth_stages;
        
        if (!isset($growthStages[$stage])) {
            return 0;
        }

        return $growthStages[$stage]['duration_hours'] ?? 24;
    }

    // ได้รับ XP reward ตาม stage ที่เติบโต
    public function getXpRewardForStage(int $stage): int
    {
        $multiplier = match($stage) {
            0 => 0.2,  // Seed
            1 => 0.5,  // Sprout  
            2 => 0.8,  // Sapling
            3 => 1.0,  // Mature
            4 => 1.5,  // Blooming
            default => 0
        };

        return (int) ($this->base_xp_reward * $multiplier);
    }

    // ได้รับ Star Seeds ตาม rarity
    public function getStarSeedsReward(): int
    {
        return match($this->rarity) {
            'common' => 10,
            'rare' => 25,
            'epic' => 50,
            'legendary' => 100,
            default => 5
        };
    }

    // ข้อมูล care requirements ที่เป็น human readable
    public function getCareRequirementsDescription(): array
    {
        $requirements = $this->care_requirements;
        
        return [
            'water_frequency' => $requirements['water_frequency'] ?? 'daily',
            'sunlight_hours' => $requirements['sunlight_hours'] ?? 6,
            'fertilizer_needed' => $requirements['fertilizer_needed'] ?? false,
            'special_care' => $requirements['special_care'] ?? null
        ];
    }

    // สร้างข้อมูล plant พร้อม default values
    public static function getDefaultPlantTypes(): array
    {
        return [
            // Fitness Plants
            [
                'name' => 'กุหลาบ',
                'category' => 'fitness',
                'rarity' => 'common',
                'description' => 'สัญลักษณ์ของความแข็งแกร่งและการออกกำลังกายหัวใจ',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ด', 'duration_hours' => 12],
                    1 => ['name' => 'หน่อ', 'duration_hours' => 48],
                    2 => ['name' => 'ต้นอ่อน', 'duration_hours' => 168],
                    3 => ['name' => 'โตเต็มที่', 'duration_hours' => 336],
                    4 => ['name' => 'ออกดอก', 'duration_hours' => 720]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 6,
                    'fertilizer_needed' => true
                ],
                'base_xp_reward' => 50,
                'unlock_level' => 1
            ],
            [
                'name' => 'ทานตะวัน',
                'category' => 'fitness',
                'rarity' => 'common',
                'description' => 'สร้างพลังงานและความแข็งแกร่งเหมือนดวงอาทิตย์',
                'growth_stages' => [
                    0 => ['name' => 'เมล็ด', 'duration_hours' => 8],
                    1 => ['name' => 'หน่อ', 'duration_hours' => 24],
                    2 => ['name' => 'ต้นอ่อน', 'duration_hours' => 120],
                    3 => ['name' => 'โตเต็มที่', 'duration_hours' => 240],
                    4 => ['name' => 'ดอกบาน', 'duration_hours' => 480]
                ],
                'care_requirements' => [
                    'water_frequency' => 'daily',
                    'sunlight_hours' => 8,
                    'fertilizer_needed' => false
                ],
                'base_xp_reward' => 60,
                'unlock_level' => 2
            ]
        ];
    }
}