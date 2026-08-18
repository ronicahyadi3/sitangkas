<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserManagementAuditEvent>
 */
class UserManagementAuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_uuid' => (string) Str::uuid(),
            'actor_user_id' => User::factory(),
            'actor_user_position_id' => null,
            'target_user_id' => User::factory(),
            'target_user_position_id' => null,
            'event_type' => $this->faker->randomElement([
                UserManagementAuditEvent::EVENT_USER_CREATED,
                UserManagementAuditEvent::EVENT_USER_UPDATED,
                UserManagementAuditEvent::EVENT_POSITION_CREATED,
                UserManagementAuditEvent::EVENT_SECURITY_LOCK,
            ]),
            'result' => UserManagementAuditEvent::RESULT_SUCCESS,
            'resource_type' => User::class,
            'resource_id' => (string) $this->faker->numberBetween(1, 999),
            'reason_code' => null,
            'reason' => null,
            'message' => $this->faker->sentence(),
            'before_state' => null,
            'after_state' => ['nama' => $this->faker->name()],
            'changed_fields' => ['nama'],
            'metadata' => null,
            'before_state_hash' => null,
            'after_state_hash' => null,
            'event_hash' => null,
            'request_id' => (string) Str::uuid(),
            'correlation_id' => null,
            'session_id_hash' => hash('sha256', $this->faker->uuid()),
            'source_channel' => 'web',
            'route_name' => 'users.index',
            'request_path' => 'users',
            'http_method' => 'POST',
            'http_status' => 200,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'occurred_at' => now(),
            'retention_until' => null,
            'created_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'result' => UserManagementAuditEvent::RESULT_FAILED,
            'http_status' => 422,
        ]);
    }

    public function forPosition(UserPosition $position): static
    {
        return $this->state(fn (): array => [
            'resource_type' => UserPosition::class,
            'resource_id' => (string) $position->getKey(),
            'target_user_id' => $position->user_id,
            'target_user_position_id' => $position->getKey(),
        ]);
    }
}
