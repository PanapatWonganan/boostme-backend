<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAccessLog extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'user_id',
        'video_id',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'started_at',
        'ended_at',
        'watch_duration',
        'seek_count',
        'speed_changes',
        'suspicious_activity'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'suspicious_activity' => 'array',
        'watch_duration' => 'integer',
        'seek_count' => 'integer',
        'speed_changes' => 'integer'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
    
    public function markSuspicious(string $reason): void
    {
        $activities = $this->suspicious_activity ?? [];
        $activities[] = [
            'reason' => $reason,
            'timestamp' => now()->toISOString()
        ];
        
        $this->update(['suspicious_activity' => $activities]);
    }
}
