<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserGarden extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'level',
        'xp',
        'star_seeds',
        'theme',
        'garden_layout',
        'last_watered_at',
        'last_visited_at'
    ];

    protected $casts = [
        'garden_layout' => 'array',
        'last_watered_at' => 'datetime',
        'last_visited_at' => 'datetime',
    ];

    // ความสัมพันธ์กับ User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ความสัมพันธ์กับพืชในสวน
    public function plants(): HasMany
    {
        return $this->hasMany(UserPlant::class, 'garden_id');
    }

    // กิจกรรมในสวน
    public function activities(): HasMany
    {
        return $this->hasMany(GardenActivity::class, 'garden_id');
    }

    // พืชที่กำลังเติบโต
    public function growingPlants(): HasMany
    {
        return $this->hasMany(UserPlant::class, 'garden_id')
                    ->where('is_fully_grown', false);
    }

    // พืชที่เติบโตเต็มที่แล้ว
    public function maturePlants(): HasMany
    {
        return $this->hasMany(UserPlant::class, 'garden_id')
                    ->where('is_fully_grown', true);
    }

    // คำนวณ XP ที่จำเป็นสำหรับ level ถัดไป
    public function getXpForNextLevelAttribute(): int
    {
        return $this->level * 1000; // XP ที่ต้องการสำหรับ level ถัดไป
    }

    // เช็คว่าพร้อม level up หรือไม่
    public function getCanLevelUpAttribute(): bool
    {
        return $this->xp >= $this->xp_for_next_level;
    }

    // เพิ่ม XP และตรวจสอบ level up
    public function addXp(int $xp): self
    {
        $this->xp += $xp;
        
        // เช็ค level up
        while ($this->can_level_up) {
            $this->level++;
            $this->xp -= $this->xp_for_next_level;
        }
        
        $this->save();
        return $this;
    }

    // เพิ่ม Star Seeds
    public function addStarSeeds(int $amount): self
    {
        $this->star_seeds += $amount;
        $this->save();
        return $this;
    }

    // ตรวจสอบว่าต้องรดน้ำหรือไม่
    public function needsWatering(): bool
    {
        if (!$this->last_watered_at) {
            return true;
        }
        
        return $this->last_watered_at->diffInHours(now()) >= 24;
    }

    // รดน้ำสวน
    public function water(): self
    {
        $this->last_watered_at = now();
        $this->save();

        // ให้ XP สำหรับการรดน้ำ
        $this->addXp(20);
        
        // บันทึกกิจกรรม
        $this->activities()->create([
            'user_id' => $this->user_id,
            'activity_type' => 'water',
            'target_type' => 'garden',
            'target_id' => $this->id,
            'xp_earned' => 20,
            'star_seeds_earned' => 5,
            'activity_data' => ['watered_at' => now()]
        ]);

        return $this;
    }
}