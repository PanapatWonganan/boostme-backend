<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GardenFriend extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
        'requested_at',
        'accepted_at'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    // ความสัมพันธ์กับผู้ส่งคำขอ
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ความสัมพันธ์กับเพื่อน
    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    // Scope สำหรับคำขอที่ยอมรับแล้ว
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    // Scope สำหรับคำขอที่รอการตอบรับ
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Scope สำหรับคำขอที่ถูกบล็อก
    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    // Scope สำหรับเพื่อนของผู้ใช้คนหนึ่ง
    public function scopeForUser($query, string $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('friend_id', $userId);
        });
    }

    // ยอมรับคำขอเป็นเพื่อน
    public function accept(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->status = 'accepted';
        $this->accepted_at = now();
        return $this->save();
    }

    // ปฏิเสธคำขอเป็นเพื่อน
    public function reject(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return $this->delete();
    }

    // บล็อกเพื่อน
    public function block(): bool
    {
        $this->status = 'blocked';
        return $this->save();
    }

    // ยกเลิกการบล็อก
    public function unblock(): bool
    {
        if ($this->status !== 'blocked') {
            return false;
        }

        $this->status = 'accepted';
        return $this->save();
    }

    // ตรวจสอบว่าสองผู้ใช้เป็นเพื่อนกันหรือไม่
    public static function areFriends(string $userId1, string $userId2): bool
    {
        return self::where(function($query) use ($userId1, $userId2) {
            $query->where('user_id', $userId1)->where('friend_id', $userId2);
        })->orWhere(function($query) use ($userId1, $userId2) {
            $query->where('user_id', $userId2)->where('friend_id', $userId1);
        })->where('status', 'accepted')->exists();
    }

    // ส่งคำขอเป็นเพื่อน
    public static function sendRequest(string $fromUserId, string $toUserId): ?self
    {
        // ตรวจสอบว่ามีคำขออยู่แล้วหรือไม่
        $existingRequest = self::where(function($query) use ($fromUserId, $toUserId) {
            $query->where('user_id', $fromUserId)->where('friend_id', $toUserId);
        })->orWhere(function($query) use ($fromUserId, $toUserId) {
            $query->where('user_id', $toUserId)->where('friend_id', $fromUserId);
        })->first();

        if ($existingRequest) {
            return null; // มีคำขออยู่แล้ว
        }

        return self::create([
            'user_id' => $fromUserId,
            'friend_id' => $toUserId,
            'status' => 'pending',
            'requested_at' => now()
        ]);
    }

    // ดึงรายชื่อเพื่อนของผู้ใช้
    public static function getFriendsList(string $userId): array
    {
        $friendships = self::forUser($userId)->accepted()->with(['user', 'friend'])->get();
        
        return $friendships->map(function($friendship) use ($userId) {
            return $friendship->user_id === $userId 
                ? $friendship->friend 
                : $friendship->user;
        })->toArray();
    }

    // ดึงคำขอเป็นเพื่อนที่รอการตอบรับ
    public static function getPendingRequests(string $userId): array
    {
        return self::where('friend_id', $userId)
                   ->pending()
                   ->with('user')
                   ->latest('requested_at')
                   ->get()
                   ->toArray();
    }
}
