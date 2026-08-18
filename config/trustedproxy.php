<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy IPs/CIDRs, REMOTE_ADDR, or "*" when the deployment
    | intentionally trusts all calling proxies. Keep this empty for local or
    | direct-to-app deployments.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
