<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'video_url',
        'duration_minutes',
        'order_index',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'order_index' => 'integer',
            'is_free' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }
    
    public function videos()
    {
        return $this->hasMany(Video::class);
    }
    
    public function primaryVideo()
    {
        // Return the latest video regardless of status for display purposes
        // Frontend will handle status checking
        return $this->hasOne(Video::class)
            ->whereNotIn('status', ['replaced', 'deleted'])
            ->latest();
    }
    
    public function readyVideo()
    {
        // Return only ready videos for streaming
        return $this->hasOne(Video::class)
            ->where('status', 'ready')
            ->latest();
    }
}
