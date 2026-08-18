<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestNetworkContext
{
    /**
     * @return array{
     *     request_id: string|null,
     *     correlation_id: string|null,
     *     ip_address: string|null,
     *     proxy_ip_address: string|null,
     *     forwarded_for: list<string>|null
     * }
     */
    public function fromRequest(Request $request): array
    {
        $forwardedFor = $this->forwardedFor($request);

        return [
            'request_id' => $this->uuidHeader($request, 'X-Request-Id'),
            'correlation_id' => $this->uuidHeader($request, 'X-Correlation-Id'),
            'ip_address' => $this->ipAddress($request),
            'proxy_ip_address' => $this->proxyIpAddress($request, $forwardedFor),
            'forwarded_for' => $forwardedFor,
        ];
    }

    private function ipAddress(Request $request): ?string
    {
        $ipAddress = $request->ip();

        return is_string($ipAddress) && filter_var($ipAddress, FILTER_VALIDATE_IP)
            ? $ipAddress
            : null;
    }

    private function proxyIpAddress(Request $request, ?array $forwardedFor): ?string
    {
        if (! $this->proxyIpAddressEnabled() || $forwardedFor === null || ! $request->isFromTrustedProxy()) {
            return null;
        }

        $remoteAddress = $request->server->get('REMOTE_ADDR');

        if (! is_string($remoteAddress) || ! filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $remoteAddress;
    }

    private function uuidHeader(Request $request, string $header): ?string
    {
        $value = $request->header($header);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || ! Str::isUuid($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<string>|null
     */
    private function forwardedFor(Request $request): ?array
    {
        if (! $this->shouldCaptureForwardedFor($request)) {
            return null;
        }

        $forwardedFor = $request->headers->get('X-Forwarded-For');

        if (! is_string($forwardedFor) || trim($forwardedFor) === '') {
            return null;
        }

        $ipAddresses = collect(explode(',', $forwardedFor))
            ->map(fn (string $ipAddress): string => trim($ipAddress))
            ->filter()
            ->values();

        if ($ipAddresses->isEmpty()) {
            return null;
        }

        if ($ipAddresses->contains(fn (string $ipAddress): bool => ! filter_var($ipAddress, FILTER_VALIDATE_IP))) {
            return null;
        }

        return $ipAddresses
            ->take($this->forwardedForMaxEntries())
            ->values()
            ->all();
    }

    private function shouldCaptureForwardedFor(Request $request): bool
    {
        return $request->isFromTrustedProxy() || $this->captureUntrustedForwardedFor();
    }

    private function forwardedForMaxEntries(): int
    {
        return max(1, (int) config('auth.audit.login_events.enrichment.network.forwarded_for_max_entries', 10));
    }

    private function captureUntrustedForwardedFor(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.network.capture_untrusted_forwarded_for', false);
    }

    private function proxyIpAddressEnabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.network.proxy_ip_address_enabled', true);
    }
}
