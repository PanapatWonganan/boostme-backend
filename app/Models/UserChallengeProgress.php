<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserChallengeProgress extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'challenge_id',
        'progress',
        'target',
        'is_completed',
        'completed_at',
        'progress_data'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'progress_data' => 'array',
    ];

    // ความสัมพันธ์กับ User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ความสัมพันธ์กับ DailyChallenge
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(DailyChallenge::class, 'challenge_id');
    }

    // Scope สำหรับ challenge ที่สำเร็จแล้ว
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    // Scope สำหรับ challenge ที่ยังไม่สำเร็จ
    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false);
    }

    // Scope สำหรับผู้ใช้คนหนึ่ง
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Scope สำหรับ challenge วันนี้
    public function scopeToday($query)
    {
        return $query->whereHas('challenge', function($q) {
            $q->where('available_date', today());
        });
    }

    // อัปเดตความก้าวหน้า
    public function updateProgress(int $increment = 1, array $progressData = []): bool
    {
        if ($this->is_completed) {
            return false; // ทำเสร็จแล้ว ไม่สามารถอัปเดตได้
        }

        $this->progress = min($this->target, $this->progress + $increment);
        
        // รวม progress_data เก่ากับใหม่
        $this->progress_data = array_merge($this->progress_data ?? [], $progressData);

        // ตรวจสอบว่าทำเสร็จหรือไม่
        if ($this->progress >= $this->target) {
            $this->markAsCompleted();
        }

        return $this->save();
    }

    // ทำเครื่องหมายว่าสำเร็จแล้ว
    public function markAsCompleted(): self
    {
        $this->is_completed = true;
        $this->completed_at = now();
        $this->progress = $this->target; // ให้แน่ใจว่าเป็น 100%
        
        // ให้รางวัล XP และ Star Seeds
        $this->awardRewards();
        
        return $this;
    }

    // ให้รางวัลเมื่อทำ challenge สำเร็จ
    private function awardRewards(): void
    {
        $user = $this->user;
        $challenge = $this->challenge;
        
        if (!$user->garden) {
            // สร้างสวนใหม่ถ้ายังไม่มี
            $user->garden()->create([
                'level' => 1,
                'xp' => 0,
                'star_seeds' => 100,
                'theme' => 'tropical'
            ]);
        }

        // ให้ XP และ Star Seeds
        $user->garden->addXp($challenge->xp_reward);
        $user->garden->addStarSeeds($challenge->star_seeds_reward);

        // บันทึกกิจกรรม
        GardenActivity::logActivity(
            $user->id,
            $user->garden->id,
            'challenge_complete',
            'challenge',
            $challenge->id,
            $challenge->xp_reward,
            $challenge->star_seeds_reward,
            [
                'challenge_name' => $challenge->name,
                'challenge_type' => $challenge->challenge_type,
                'completed_at' => $this->completed_at
            ]
        );
    }

    // คำนวณเปอร์เซ็นต์ความก้าวหน้า
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target <= 0) return 0;
        return min(100, ($this->progress / $this->target) * 100);
    }

    // ตรวจสอบว่าเกือบจะเสร็จแล้ว (80% ขึ้นไป)
    public function getIsNearCompletionAttribute(): bool
    {
        return $this->progress_percentage >= 80;
    }

    // ความก้าวหน้าในรูปแบบข้อความ
    public function getProgressTextAttribute(): string
    {
        return "{$this->progress}/{$this->target}";
    }

    // เหลืออีกเท่าไหร่จึงจะเสร็จ
    public function getRemainingAttribute(): int
    {
        return max(0, $this->target - $this->progress);
    }

    // สถานะของ challenge
    public function getStatusAttribute(): string
    {
        if ($this->is_completed) {
            return 'completed';
        } elseif ($this->progress > 0) {
            return 'in_progress';
        } else {
            return 'not_started';
        }
    }

    // ข้อความสถานะ
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'completed' => 'สำเร็จแล้ว',
            'in_progress' => 'กำลังดำเนินการ',
            'not_started' => 'ยังไม่เริ่ม',
            default => 'ไม่ทราบสถานะ'
        };
    }

    // สีของสถานะ
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'green',
            'in_progress' => 'yellow',
            'not_started' => 'gray',
            default => 'gray'
        };
    }

    // ไอคอนของสถานะ
    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            'completed' => '✅',
            'in_progress' => '⏳',
            'not_started' => '❌',
            default => '❓'
        };
    }

    // รีเซ็ตความก้าวหน้า (สำหรับ challenge ใหม่)
    public function reset(): self
    {
        $this->progress = 0;
        $this->is_completed = false;
        $this->completed_at = null;
        $this->progress_data = [];
        $this->save();
        
        return $this;
    }
}