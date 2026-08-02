<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security_headers.enabled', true)) {
            return $response;
        }

        foreach ($this->headers($request) as $header => $value) {
            if ($value === null || $value === '' || $response->headers->has($header)) {
                continue;
            }

            $response->headers->set($header, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string|null>
     */
    private function headers(Request $request): array
    {
        $headers = config('security_headers.headers', []);

        if (! is_array($headers)) {
            $headers = [];
        }

        if ($this->shouldAddHsts($request)) {
            $headers['Strict-Transport-Security'] = $this->hstsHeader();
        }

        if ($this->shouldAddContentSecurityPolicy()) {
            $headers[$this->contentSecurityPolicyHeaderName()] = $this->contentSecurityPolicy();
        }

        return $headers;
    }

    private function shouldAddHsts(Request $request): bool
    {
        if (! config('security_headers.hsts.enabled', false)) {
            return false;
        }

        if (! config('security_headers.hsts.only_when_secure', true)) {
            return true;
        }

        return $request->isSecure();
    }

    private function hstsHeader(): string
    {
        $directives = [
            'max-age='.(int) config('security_headers.hsts.max_age', 31536000),
        ];

        if (config('security_headers.hsts.include_subdomains', true)) {
            $directives[] = 'includeSubDomains';
        }

        if (config('security_headers.hsts.preload', false)) {
            $directives[] = 'preload';
        }

        return implode('; ', $directives);
    }

    private function shouldAddContentSecurityPolicy(): bool
    {
        return (bool) config('security_headers.csp.enabled', true)
            && $this->contentSecurityPolicy() !== '';
    }

    private function contentSecurityPolicyHeaderName(): string
    {
        if (config('security_headers.csp.report_only', false)) {
            return 'Content-Security-Policy-Report-Only';
        }

        return 'Content-Security-Policy';
    }

    private function contentSecurityPolicy(): string
    {
        $directives = config('security_headers.csp.directives', []);

        if (! is_array($directives)) {
            return '';
        }

        $policy = [];

        foreach ($directives as $directive => $values) {
            if (! is_string($directive) || $directive === '' || $values === false || $values === null) {
                continue;
            }

            if ($values === true || $values === []) {
                $policy[] = $directive;

                continue;
            }

            $valueList = is_array($values)
                ? array_filter(array_map(fn (mixed $value): string => trim((string) $value), $values))
                : [trim((string) $values)];

            if ($valueList === [] || $valueList === ['']) {
                continue;
            }

            $policy[] = trim($directive.' '.implode(' ', $valueList));
        }

        return implode('; ', $policy);
    }
}
