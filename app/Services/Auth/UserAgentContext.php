<?php

namespace App\Services\Auth;

use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class UserAgentContext
{
    private const PROVIDER_MATOMO_DEVICE_DETECTOR = 'matomo_device_detector';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     device_type: string|null,
     *     device_name: string|null,
     *     browser_name: string|null,
     *     browser_version: string|null,
     *     platform_name: string|null,
     *     platform_version: string|null
     * }
     */
    public function fromRequest(Request $request, array $overrides = []): array
    {
        $context = $this->emptyContext();

        if (! $this->enabled() || $this->provider() !== self::PROVIDER_MATOMO_DEVICE_DETECTOR) {
            return $context;
        }

        $userAgent = $overrides['user_agent'] ?? $request->userAgent();

        if (! is_string($userAgent) || trim($userAgent) === '') {
            return $context;
        }

        try {
            return $this->parseWithMatomoDeviceDetector($userAgent);
        } catch (Throwable) {
            return $context;
        }
    }

    /**
     * @return array{
     *     device_type: string|null,
     *     device_name: string|null,
     *     browser_name: string|null,
     *     browser_version: string|null,
     *     platform_name: string|null,
     *     platform_version: string|null
     * }
     */
    private function parseWithMatomoDeviceDetector(string $userAgent): array
    {
        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        if ($detector->isBot()) {
            return [
                ...$this->emptyContext(),
                'device_type' => 'bot',
            ];
        }

        return [
            'device_type' => $this->clean($detector->getDeviceName(), 50),
            'device_name' => $this->deviceName($detector),
            'browser_name' => $this->clean($detector->getClient('name'), 100),
            'browser_version' => $this->clean($detector->getClient('version'), 50),
            'platform_name' => $this->clean($detector->getOs('name'), 100),
            'platform_version' => $this->clean($detector->getOs('version'), 50),
        ];
    }

    private function enabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.user_agent.enabled', true);
    }

    private function provider(): string
    {
        $provider = config('auth.audit.login_events.enrichment.user_agent.provider', self::PROVIDER_MATOMO_DEVICE_DETECTOR);

        return is_string($provider) && $provider !== ''
            ? $provider
            : self::PROVIDER_MATOMO_DEVICE_DETECTOR;
    }

    private function deviceName(DeviceDetector $detector): ?string
    {
        $brand = $this->clean($detector->getBrandName(), 100);
        $model = $this->clean($detector->getModel(), 100);

        $deviceName = trim(implode(' ', array_filter([$brand, $model])));

        if ($deviceName === '') {
            return null;
        }

        return Str::limit($deviceName, 100, '');
    }

    private function clean(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === DeviceDetector::UNKNOWN) {
            return null;
        }

        return Str::limit($value, $limit, '');
    }

    /**
     * @return array{
     *     device_type: null,
     *     device_name: null,
     *     browser_name: null,
     *     browser_version: null,
     *     platform_name: null,
     *     platform_version: null
     * }
     */
    private function emptyContext(): array
    {
        return [
            'device_type' => null,
            'device_name' => null,
            'browser_name' => null,
            'browser_version' => null,
            'platform_name' => null,
            'platform_version' => null,
        ];
    }
}
