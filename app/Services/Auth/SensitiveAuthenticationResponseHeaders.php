<?php

namespace App\Services\Auth;

use Illuminate\Http\Response;

class SensitiveAuthenticationResponseHeaders
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function noStoreView(string $view, array $data = [], int $status = 200): Response
    {
        return response()
            ->view($view, $data, $status)
            ->withHeaders($this->noStoreHeaders());
    }

    /**
     * @return array<string, string>
     */
    public function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Surrogate-Control' => 'no-store',
        ];
    }
}
