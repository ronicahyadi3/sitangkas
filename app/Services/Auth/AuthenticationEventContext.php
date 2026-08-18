<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

class AuthenticationEventContext
{
    public function __construct(
        private RequestNetworkContext $requestNetworkContext,
        private IpGeolocationContext $ipGeolocationContext,
        private IpRiskContext $ipRiskContext,
        private UserAgentContext $userAgentContext,
        private ClientSignalContext $clientSignalContext,
        private ApplicationVersionContext $applicationVersionContext
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function defaults(Request $request, array $overrides = []): array
    {
        $networkContext = $this->requestNetworkContext->fromRequest($request);

        return [
            ...$networkContext,
            ...$this->ipGeolocationContext->fromIp($networkContext['ip_address'] ?? null),
            ...$this->ipRiskContext->fromIp($networkContext['ip_address'] ?? null),
            ...$this->userAgentContext->fromRequest($request, $overrides),
            ...$this->clientSignalContext->fromRequest($request),
            'application_version' => $this->applicationVersionContext->value(),
        ];
    }
}
