<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Achievement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'category',
        'description',
        'badge_icon',
        'rarity',
        'criteria',
        'xp_reward',
        'star_seeds_reward',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    // ความสัมพันธ์กับผู้ใช้ที่ได้รับ achievement นี้
    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    // ผู้ใช้ที่ได้รับ achievement นี้
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_achievements')
                    ->withTimestamps()
                    ->withPivot(['earned_at', 'progress_data']);
    }

    // Scope สำหรับ achievement ที่ active
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    // ตรวจสอบว่าผู้ใช้ได้รับ achievement นี้แล้วหรือไม่
    public function isEarnedByUser(string $userId): bool
    {
        return $this->userAchievements()
                    ->where('user_id', $userId)
                    ->exists();
    }

    // ให้ achievement กับผู้ใช้
    public function awardToUser(string $userId, array $progressData = []): UserAchievement
    {
        if ($this->isEarnedByUser($userId)) {
            throw new \Exception('User already has this achievement');
        }

        return $this->userAchievements()->create([
            'user_id' => $userId,
            'earned_at' => now(),
            'progress_data' => $progressData
        ]);
    }

    // ตรวจสอบเงื่อนไขการได้รับ achievement
    public function checkCriteria(User $user): bool
    {
        $criteria = $this->criteria;
        
        // ตัวอย่างการตรวจสอบเงื่อนไข
        switch ($criteria['type'] ?? null) {
            case 'course_complete':
                return $this->checkCourseCompleteCriteria($user, $criteria);
            case 'plant_grow':
                return $this->checkPlantGrowCriteria($user, $criteria);
            case 'consecutive_days':
                return $this->checkConsecutiveDaysCriteria($user, $criteria);
            case 'level_reach':
                return $this->checkLevelReachCriteria($user, $criteria);
            default:
                return false;
        }
    }

    private function checkCourseCompleteCriteria(User $user, array $criteria): bool
    {
        $requiredCount = $criteria['count'] ?? 1;
        
        // ตรวจสอบจำนวนบทเรียนที่เรียนจบแล้วจริงๆ
        $completedLessonsCount = LessonProgress::where('user_id', $user->id)
            ->where('is_completed', true)
            ->where('completed_at', '!=', null)
            ->distinct('lesson_id')
            ->count('lesson_id');
        
        return $completedLessonsCount >= $requiredCount;
    }

    private function checkPlantGrowCriteria(User $user, array $criteria): bool
    {
        $requiredCount = $criteria['count'] ?? 1;
        $garden = $user->garden;
        
        if (!$garden) return false;
        
        $grownPlantsCount = $garden->maturePlants()->count();
        return $grownPlantsCount >= $requiredCount;
    }

    private function checkConsecutiveDaysCriteria(User $user, array $criteria): bool
    {
        $requiredDays = $criteria['days'] ?? 7;
        // Logic สำหรับตรวจสอบการเข้าใช้ติดต่อกัน
        return true; // Placeholder
    }

    private function checkLevelReachCriteria(User $user, array $criteria): bool
    {
        $requiredLevel = $criteria['level'] ?? 5;
        $garden = $user->garden;
        
        if (!$garden) return false;
        
        return $garden->level >= $requiredLevel;
    }

    // สร้างข้อมูล achievement พื้นฐาน
    public static function getDefaultAchievements(): array
    {
        return [
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
                'description' => 'เรียนบทเรียนจบ 1 บทเรียน',
                'badge_icon' => '📚',
                'rarity' => 'common',
                'criteria' => ['type' => 'course_complete', 'count' => 1],
                'xp_reward' => 200,
                'star_seeds_reward' => 100,
                'sort_order' => 2
            ],
            [
                'name' => 'นักสวนระดับ 5',
                'category' => 'special',
                'description' => 'เลื่อนระดับสวนถึง Level 5',
                'badge_icon' => '🏆',
                'rarity' => 'rare',
                'criteria' => ['type' => 'level_reach', 'level' => 5],
                'xp_reward' => 500,
                'star_seeds_reward' => 250,
                'sort_order' => 10
            ]
        ];
    }
}