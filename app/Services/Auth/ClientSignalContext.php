<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientSignalContext
{
    /**
     * @return array{client_timezone: string|null}
     */
    public function fromRequest(Request $request): array
    {
        return [
            'client_timezone' => $this->clientTimezone($request),
        ];
    }

    private function clientTimezone(Request $request): ?string
    {
        if (! $this->clientTimezoneEnabled()) {
            return null;
        }

        $timezone = $request->input('client_timezone', $request->header('X-Client-Timezone'));

        if (! is_scalar($timezone)) {
            return null;
        }

        $timezone = trim((string) $timezone);

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return null;
        }

        return Str::limit($timezone, 100, '');
    }

    private function clientTimezoneEnabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.client_timezone.enabled', true);
    }
}
