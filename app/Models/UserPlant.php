<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class UserPlant extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'garden_id',
        'plant_type_id',
        'custom_name',
        'stage',
        'health',
        'growth_points',
        'position',
        'planted_at',
        'last_watered_at',
        'next_water_at',
        'harvested_at',
        'is_fully_grown'
    ];

    protected $casts = [
        'position' => 'array',
        'planted_at' => 'datetime',
        'last_watered_at' => 'datetime',
        'next_water_at' => 'datetime',
        'harvested_at' => 'datetime',
        'is_fully_grown' => 'boolean',
    ];

    // ความสัมพันธ์กับ User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ความสัมพันธ์กับ UserGarden
    public function garden(): BelongsTo
    {
        return $this->belongsTo(UserGarden::class, 'garden_id');
    }

    // ความสัมพันธ์กับ PlantType
    public function plantType(): BelongsTo
    {
        return $this->belongsTo(PlantType::class, 'plant_type_id');
    }

    // ตรวจสอบว่าต้องรดน้ำหรือไม่
    public function needsWatering(): bool
    {
        if (!$this->next_water_at) {
            return true;
        }
        
        return now()->gte($this->next_water_at);
    }

    // รดน้ำพืช
    public function water(): self
    {
        $this->last_watered_at = now();
        $this->next_water_at = now()->addDay(); // รดน้ำทุกวัน
        $this->health = min(100, $this->health + 10); // เพิ่มสุขภาพ
        
        // เพิ่มคะแนนการเติบโตตามความหายาก
        $basePoints = 10;
        $rarityBonus = match($this->plantType->rarity) {
            'common' => 0,
            'rare' => 5,      // รวม 15 points
            'epic' => 10,     // รวม 20 points
            'legendary' => 15, // รวม 25 points
            default => 0
        };
        $this->growth_points += ($basePoints + $rarityBonus);
        
        // ตรวจสอบการเติบโตไปขั้นถัดไป
        $this->checkGrowthProgress();
        
        $this->save();

        // บันทึกกิจกรรม
        GardenActivity::create([
            'user_id' => $this->user_id,
            'garden_id' => $this->garden_id,
            'activity_type' => 'water',
            'target_type' => 'plant',
            'target_id' => $this->id,
            'xp_earned' => 5,
            'star_seeds_earned' => 2,
            'activity_data' => [
                'plant_name' => $this->getDisplayName(),
                'stage_after' => $this->stage
            ]
        ]);

        return $this;
    }

    // ตรวจสอบและอัปเดตการเติบโต
    public function checkGrowthProgress(): bool
    {
        if ($this->is_fully_grown) {
            return false;
        }

        $requiredPoints = $this->getGrowthPointsRequired();
        
        if ($this->growth_points >= $requiredPoints && $this->stage < 4) {
            return $this->growToNextStage();
        }

        return false;
    }

    // เติบโตไปขั้นถัดไป
    public function growToNextStage(): bool
    {
        if ($this->stage >= 4) {
            $this->is_fully_grown = true;
            $this->save();
            return false;
        }

        $oldStage = $this->stage;
        $this->stage++;
        $this->growth_points = 0; // รีเซ็ตคะแนนการเติบโต
        
        if ($this->stage >= 4) {
            $this->is_fully_grown = true;
        }
        
        $this->save();

        // ให้ XP และ Star Seeds สำหรับการเติบโต
        $xpReward = $this->plantType->getXpRewardForStage($this->stage);
        $starSeedsReward = $this->plantType->getStarSeedsReward();
        
        $this->garden->addXp($xpReward);
        $this->garden->addStarSeeds($starSeedsReward);

        // บันทึกกิจกรรม
        GardenActivity::create([
            'user_id' => $this->user_id,
            'garden_id' => $this->garden_id,
            'activity_type' => 'grow',
            'target_type' => 'plant',
            'target_id' => $this->id,
            'xp_earned' => $xpReward,
            'star_seeds_earned' => $starSeedsReward,
            'activity_data' => [
                'plant_name' => $this->getDisplayName(),
                'old_stage' => $oldStage,
                'new_stage' => $this->stage,
                'stage_name' => $this->getCurrentStageName()
            ]
        ]);

        return true;
    }

    // คะแนนที่ต้องการสำหรับเติบโตไปขั้นถัดไป
    public function getGrowthPointsRequired(): int
    {
        return match($this->stage) {
            0 => 20,   // Seed → Sprout (2 วัน)
            1 => 30,   // Sprout → Sapling (3 วัน)
            2 => 50,   // Sapling → Mature (5 วัน)
            3 => 70,   // Mature → Blooming (7 วัน)
            default => 999999
        };
    }

    // ชื่อของขั้นการเติบโตปัจจุบัน
    public function getCurrentStageName(): string
    {
        $stages = $this->plantType->growth_stages;
        return $stages[$this->stage]['name'] ?? 'Unknown';
    }

    // ความก้าวหน้าของการเติบโต (percentage)
    public function getGrowthProgress(): float
    {
        if ($this->is_fully_grown) {
            return 100.0;
        }

        $required = $this->getGrowthPointsRequired();
        return min(100.0, ($this->growth_points / $required) * 100);
    }

    // ชื่อที่แสดงผล (custom name หรือ plant type name)
    public function getDisplayName(): string
    {
        return $this->custom_name ?: $this->plantType->name;
    }

    // ตรวจสอบสุขภาพของพืช
    public function getHealthStatus(): string
    {
        return match(true) {
            $this->health >= 80 => 'excellent',
            $this->health >= 60 => 'good',
            $this->health >= 40 => 'fair',
            $this->health >= 20 => 'poor',
            default => 'critical'
        };
    }

    // เก็บเกี่ยวพืช (สำหรับพืชที่เติบโตเต็มที่)
    public function harvest(): bool
    {
        if (!$this->is_fully_grown || $this->harvested_at) {
            return false;
        }

        $this->harvested_at = now();
        $this->save();

        // ให้รางวัลพิเศษสำหรับการเก็บเกี่ยว
        $bonusXp = $this->plantType->base_xp_reward;
        $bonusStarSeeds = $this->plantType->getStarSeedsReward() * 2;
        
        $this->garden->addXp($bonusXp);
        $this->garden->addStarSeeds($bonusStarSeeds);

        // บันทึกกิจกรรม
        GardenActivity::create([
            'user_id' => $this->user_id,
            'garden_id' => $this->garden_id,
            'activity_type' => 'harvest',
            'target_type' => 'plant',
            'target_id' => $this->id,
            'xp_earned' => $bonusXp,
            'star_seeds_earned' => $bonusStarSeeds,
            'activity_data' => [
                'plant_name' => $this->getDisplayName(),
                'plant_type' => $this->plantType->name,
                'category' => $this->plantType->category
            ]
        ]);

        return true;
    }

    // เพิ่มคะแนนการเติบโตจากกิจกรรม
    public function addGrowthPoints(int $points, string $activityType = 'activity'): self
    {
        $this->growth_points += $points;
        $this->checkGrowthProgress();
        $this->save();

        return $this;
    }

    // Scope สำหรับกรองพืชที่ต้องรดน้ำ
    public function scopeNeedsWatering($query)
    {
        return $query->where(function($q) {
            $q->whereNull('next_water_at')
              ->orWhere('next_water_at', '<=', now());
        })->where('is_fully_grown', false);
    }

    // Scope สำหรับกรองพืชที่เติบโตเต็มที่
    public function scopeFullyGrown($query)
    {
        return $query->where('is_fully_grown', true);
    }

    // Scope สำหรับกรองพืชที่พร้อมเก็บเกี่ยว
    public function scopeReadyToHarvest($query)
    {
        return $query->where('is_fully_grown', true)
                     ->whereNull('harvested_at');
    }
}