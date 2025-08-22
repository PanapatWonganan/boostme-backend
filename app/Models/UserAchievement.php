<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserAchievement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'achievement_id',
        'earned_at',
        'progress_data'
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'progress_data' => 'array',
    ];

    // ความสัมพันธ์กับ User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ความสัมพันธ์กับ Achievement
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    // Scope สำหรับ achievement ที่ได้รับล่าสุด
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('earned_at', '>=', now()->subDays($days));
    }

    // Scope สำหรับกรองตาม category ของ achievement
    public function scopeByCategory($query, string $category)
    {
        return $query->whereHas('achievement', function($q) use ($category) {
            $q->where('category', $category);
        });
    }

    // Scope สำหรับกรองตาม rarity ของ achievement
    public function scopeByRarity($query, string $rarity)
    {
        return $query->whereHas('achievement', function($q) use ($rarity) {
            $q->where('rarity', $rarity);
        });
    }

    // ได้รับเมื่อไหร่ในรูปแบบ human readable
    public function getEarnedAtHumanAttribute(): string
    {
        return $this->earned_at->diffForHumans();
    }

    // ได้รับในวันไหน
    public function getEarnedDateAttribute(): string
    {
        return $this->earned_at->format('d/m/Y');
    }
}