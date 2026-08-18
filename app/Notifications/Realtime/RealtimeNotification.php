<?php

namespace App\Notifications\Realtime;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class RealtimeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public ?string $body = null,
        public string $severity = 'info',
        public ?string $actionUrl = null,
        public array $data = [],
        public ?int $actorUserId = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->payload()))
            ->onQueue('broadcasts');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function broadcastType(): string
    {
        return 'sitangkas.realtime.notification';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'severity' => $this->severity,
            'action_url' => $this->actionUrl,
            'actor_user_id' => $this->actorUserId,
            'data' => $this->data,
        ];
    }
}
