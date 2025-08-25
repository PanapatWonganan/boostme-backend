<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'title',
        'lesson_id',
        'original_filename',
        'original_path',
        'hls_path',
        'processed_path',
        'encryption_key',
        'duration_seconds',
        'file_size',
        'mime_type',
        'status',
        'processing_error',
        'metadata'
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'duration_seconds' => 'integer'
    ];
    
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
    
    public function accessLogs(): HasMany
    {
        return $this->hasMany(VideoAccessLog::class);
    }
    
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
    
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
    
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
    
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_seconds) {
            return '00:00';
        }
        
        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
    
    public function getFormattedSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
