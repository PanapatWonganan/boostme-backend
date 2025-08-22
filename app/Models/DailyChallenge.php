<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class DailyChallenge extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'challenge_type',
        'requirements',
        'xp_reward',
        'star_seeds_reward',
        'available_date',
        'is_active'
    ];

    protected $casts = [
        'requirements' => 'array',
        'available_date' => 'date',
        'is_active' => 'boolean',
    ];

    // ความสัมพันธ์กับความก้าวหน้าของผู้ใช้
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserChallengeProgress::class, 'challenge_id');
    }

    // ผู้ใช้ที่ทำ challenge นี้สำเร็จแล้ว
    public function completedUsers()
    {
        return $this->belongsToMany(User::class, 'user_challenge_progress')
                    ->wherePivot('is_completed', true)
                    ->withTimestamps()
                    ->withPivot(['progress', 'target', 'completed_at', 'progress_data']);
    }

    // Scope สำหรับ challenge ที่ active
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope สำหรับ challenge วันนี้
    public function scopeToday($query)
    {
        return $query->where('available_date', today());
    }

    // Scope สำหรับ challenge ในช่วงวันที่
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('available_date', [$startDate, $endDate]);
    }

    // Scope สำหรับกรองตามประเภท
    public function scopeByType($query, string $type)
    {
        return $query->where('challenge_type', $type);
    }

    // ตรวจสอบว่าผู้ใช้ทำ challenge นี้สำเร็จแล้วหรือไม่
    public function isCompletedByUser(string $userId): bool
    {
        return $this->userProgress()
                    ->where('user_id', $userId)
                    ->where('is_completed', true)
                    ->exists();
    }

    // ดึงความก้าวหน้าของผู้ใช้ใน challenge นี้
    public function getUserProgress(string $userId): ?UserChallengeProgress
    {
        return $this->userProgress()
                    ->where('user_id', $userId)
                    ->first();
    }

    // เริ่มต้น challenge สำหรับผู้ใช้
    public function startForUser(string $userId): UserChallengeProgress
    {
        // ตรวจสอบว่าเริ่มแล้วหรือไม่
        $existingProgress = $this->getUserProgress($userId);
        if ($existingProgress) {
            return $existingProgress;
        }

        // สร้าง progress ใหม่
        return $this->userProgress()->create([
            'user_id' => $userId,
            'progress' => 0,
            'target' => $this->requirements['target'] ?? 1,
            'is_completed' => false,
            'progress_data' => []
        ]);
    }

    // อัปเดตความก้าวหน้าของผู้ใช้
    public function updateUserProgress(string $userId, int $progressIncrement = 1, array $progressData = []): bool
    {
        $userProgress = $this->getUserProgress($userId);
        if (!$userProgress) {
            $userProgress = $this->startForUser($userId);
        }

        return $userProgress->updateProgress($progressIncrement, $progressData);
    }

    // ตรวจสอบเงื่อนไขการทำ challenge
    public function checkCompletion(User $user): bool
    {
        $requirements = $this->requirements;
        
        switch ($this->challenge_type) {
            case 'course_complete':
                return $this->checkCourseCompletion($user, $requirements);
            case 'video_watch':
                return $this->checkVideoWatch($user, $requirements);
            case 'login':
                return $this->checkLogin($user, $requirements);
            case 'plant_care':
                return $this->checkPlantCare($user, $requirements);
            default:
                return false;
        }
    }

    private function checkCourseCompletion(User $user, array $requirements): bool
    {
        // Logic สำหรับตรวจสอบการเรียนจบคอร์ส
        return true; // Placeholder
    }

    private function checkVideoWatch(User $user, array $requirements): bool
    {
        // Logic สำหรับตรวจสอบการดูวิดีโอ
        return true; // Placeholder
    }

    private function checkLogin(User $user, array $requirements): bool
    {
        // Logic สำหรับตรวจสอบการเข้าสู่ระบบ
        return true; // Placeholder
    }

    private function checkPlantCare(User $user, array $requirements): bool
    {
        $requiredCount = $requirements['count'] ?? 1;
        $garden = $user->garden;
        
        if (!$garden) return false;
        
        // นับกิจกรรมรดน้ำวันนี้
        $todayWateringCount = $garden->activities()
                                    ->where('activity_type', 'water')
                                    ->whereDate('created_at', today())
                                    ->count();
        
        return $todayWateringCount >= $requiredCount;
    }

    // สร้าง challenge ประจำวัน
    public static function createDailyChallenge(Carbon $date): array
    {
        $challenges = [
            [
                'name' => 'รดน้ำสวนประจำวัน',
                'description' => 'รดน้ำพืชในสวนของคุณอย่างน้อย 3 ต้น',
                'challenge_type' => 'plant_care',
                'requirements' => ['type' => 'water_plants', 'count' => 3],
                'xp_reward' => 100,
                'star_seeds_reward' => 50
            ],
            [
                'name' => 'นักเรียนขยัน',
                'description' => 'เรียนบทเรียนจบ 1 บทเรียน',
                'challenge_type' => 'course_complete',
                'requirements' => ['type' => 'complete_lesson', 'count' => 1],
                'xp_reward' => 150,
                'star_seeds_reward' => 75
            ],
            [
                'name' => 'เข้าสู่ระบบประจำวัน',
                'description' => 'เข้าใช้ระบบอย่างน้อย 1 ครั้ง',
                'challenge_type' => 'login',
                'requirements' => ['type' => 'daily_login', 'count' => 1],
                'xp_reward' => 50,
                'star_seeds_reward' => 25
            ]
        ];

        $createdChallenges = [];
        foreach ($challenges as $challengeData) {
            $challengeData['available_date'] = $date;
            $challengeData['is_active'] = true;
            
            $createdChallenges[] = self::create($challengeData);
        }

        return $createdChallenges;
    }

    // ดึง challenge วันนี้สำหรับผู้ใช้
    public static function getTodayChallengesForUser(string $userId): array
    {
        $challenges = self::today()->active()->with(['userProgress' => function($query) use ($userId) {
            $query->where('user_id', $userId);
        }])->get();

        return $challenges->map(function($challenge) {
            $progress = $challenge->userProgress->first();
            return [
                'id' => $challenge->id,
                'name' => $challenge->name,
                'description' => $challenge->description,
                'challenge_type' => $challenge->challenge_type,
                'xp_reward' => $challenge->xp_reward,
                'star_seeds_reward' => $challenge->star_seeds_reward,
                'is_completed' => $progress ? $progress->is_completed : false,
                'progress' => $progress ? $progress->progress : 0,
                'target' => $progress ? $progress->target : ($challenge->requirements['count'] ?? 1)
            ];
        })->toArray();
    }
}
