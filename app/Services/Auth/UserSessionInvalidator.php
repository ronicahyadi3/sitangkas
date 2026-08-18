<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserSessionInvalidator
{
    /**
     * @return array{remember_token: string, remember_token_expires_at: null, sessions_invalidated_at: Carbon}
     */
    public function invalidationFields(?Carbon $invalidatedAt = null): array
    {
        return [
            'remember_token' => Str::random(60),
            'remember_token_expires_at' => null,
            'sessions_invalidated_at' => $invalidatedAt ?? now(),
        ];
    }

    public function revokeDatabaseSessions(User $user): int
    {
        if ((string) config('session.driver', 'database') !== 'database') {
            return 0;
        }

        $sessionTable = $this->sessionTable();

        if (! $this->sessionTableHasUserIdColumn($sessionTable)) {
            return 0;
        }

        $sessionConnection = $this->sessionConnection();
        $query = $sessionConnection === null
            ? DB::table($sessionTable)
            : DB::connection($sessionConnection)->table($sessionTable);

        return $query->where('user_id', $user->getKey())->delete();
    }

    private function sessionTableHasUserIdColumn(string $sessionTable): bool
    {
        $sessionConnection = $this->sessionConnection();

        if ($sessionConnection === null) {
            return Schema::hasTable($sessionTable)
                && Schema::hasColumn($sessionTable, 'user_id');
        }

        return Schema::connection($sessionConnection)->hasTable($sessionTable)
            && Schema::connection($sessionConnection)->hasColumn($sessionTable, 'user_id');
    }

    private function sessionTable(): string
    {
        $sessionTable = config('session.table', 'sessions');

        return is_string($sessionTable) && $sessionTable !== '' ? $sessionTable : 'sessions';
    }

    private function sessionConnection(): ?string
    {
        $sessionConnection = config('session.connection');

        return is_string($sessionConnection) && $sessionConnection !== '' ? $sessionConnection : null;
    }
}
