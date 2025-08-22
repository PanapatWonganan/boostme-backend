<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'instructor_id',
        'category_id',
        'level',
        'duration_weeks',
        'price',
        'original_price',
        'thumbnail_url',
        'trailer_video_url',
        'is_published',
        'rating',
        'total_students',
        'total_lessons',
    ];

    protected function casts(): array
    {
        return [
            'duration_weeks' => 'integer',
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'is_published' => 'boolean',
            'rating' => 'decimal:1',
            'total_students' => 'integer',
            'total_lessons' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order_index');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
