<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password_hash',
        'full_name',
        'avatar_url',
        'role',
        'email_verified',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the password attribute for authentication
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the password for authentication (Laravel default)
     */
    public function getPasswordAttribute()
    {
        return $this->password_hash;
    }

    /**
     * Set the password attribute
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = $value;
    }

    /**
     * Get the name attribute for Filament
     */
    public function getNameAttribute()
    {
        return $this->full_name;
    }

    /**
     * Get Filament name
     */
    public function getFilamentName(): string
    {
        return $this->full_name ?? 'User';
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName()
    {
        return 'email';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier()
    {
        return $this->getAttribute($this->getAuthIdentifierName());
    }

    /**
     * Check if user can access Filament admin panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Relationships
     */
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    // Wellness Garden Relationships
    public function garden()
    {
        return $this->hasOne(UserGarden::class);
    }

    public function plants()
    {
        return $this->hasMany(UserPlant::class);
    }

    public function gardenActivities()
    {
        return $this->hasMany(GardenActivity::class);
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
                    ->withTimestamps()
                    ->withPivot(['earned_at', 'progress_data']);
    }

    public function userAchievements()
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function challengeProgress()
    {
        return $this->hasMany(UserChallengeProgress::class);
    }

    // Garden friend relationships
    public function sentFriendRequests()
    {
        return $this->hasMany(GardenFriend::class, 'user_id');
    }

    public function receivedFriendRequests()
    {
        return $this->hasMany(GardenFriend::class, 'friend_id');
    }

    // Helper methods for Garden
    public function getOrCreateGarden()
    {
        // ใช้ firstOrCreate เพื่อป้องกันการสร้างซ้ำและดึงข้อมูลที่ถูกต้อง
        return $this->garden()->firstOrCreate(
            ['user_id' => $this->id],
            [
                'level' => 1,
                'xp' => 0,
                'star_seeds' => 100,
                'theme' => 'tropical',
                'last_visited_at' => now()
            ]
        );
    }

    public function hasGarden(): bool
    {
        return $this->garden()->exists();
    }
}
