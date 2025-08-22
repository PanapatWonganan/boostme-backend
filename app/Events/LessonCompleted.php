<?php

namespace App\Events;

use App\Models\User;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LessonCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public Lesson $lesson;
    public LessonProgress $lessonProgress;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Lesson $lesson, LessonProgress $lessonProgress)
    {
        $this->user = $user;
        $this->lesson = $lesson;
        $this->lessonProgress = $lessonProgress;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }
}