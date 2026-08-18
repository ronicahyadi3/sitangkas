<?php

namespace App\Services\Realtime;

use App\Events\Realtime\RealtimeMessageCreated;
use App\Models\Realtime\RealtimeMessage;
use App\Models\User;
use App\Models\UserPosition;
use App\Notifications\Realtime\RealtimeNotification;

class RealtimeMessenger
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(
        User $recipient,
        string $title,
        ?string $body = null,
        string $severity = RealtimeMessage::SEVERITY_INFO,
        ?string $actionUrl = null,
        array $data = [],
        ?User $actor = null,
    ): void {
        $recipient->notify(
            (new RealtimeNotification(
                title: $title,
                body: $body,
                severity: $severity,
                actionUrl: $actionUrl,
                data: $data,
                actorUserId: $actor?->getKey(),
            ))->afterCommit()
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendMessage(
        User $recipient,
        string $title,
        ?string $body = null,
        string $severity = RealtimeMessage::SEVERITY_INFO,
        ?string $actionUrl = null,
        array $data = [],
        ?User $sender = null,
        ?UserPosition $senderUserPosition = null,
        ?UserPosition $recipientUserPosition = null,
        string $type = RealtimeMessage::TYPE_HELPER,
    ): RealtimeMessage {
        $message = RealtimeMessage::create([
            'sender_user_id' => $sender?->getKey(),
            'recipient_user_id' => $recipient->getKey(),
            'sender_user_position_id' => $senderUserPosition?->getKey(),
            'recipient_user_position_id' => $recipientUserPosition?->getKey(),
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'data' => $data,
            'broadcasted_at' => now(),
        ]);

        broadcast(new RealtimeMessageCreated($message));

        return $message;
    }
}
