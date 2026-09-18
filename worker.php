<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

require __DIR__.'/vendor/autoload.php';

if (! function_exists('frankenphp_handle_request')) {
    fwrite(STDERR, "This worker must be run by FrankenPHP.\n");
    exit(1);
}

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$maxRequests = max(1, (int) ($_SERVER['MAX_REQUESTS'] ?? 500));

$handler = static function () use ($app, $kernel): void {
    $request = Request::capture();

    try {
        $response = $kernel->handle($request);
    } catch (Throwable $e) {
        report($e);

        $response = new Response(
            config('app.debug') ? (string) $e : 'Internal Server Error',
            500,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    try {
        $response->send();
    } finally {
        try {
            $kernel->terminate($request, $response);
        } catch (Throwable $e) {
            report($e);
        }

        $app->forgetScopedInstances();
        $app->forgetInstance('request');
        Facade::clearResolvedInstances();
    }
};

for ($i = 0; $i < $maxRequests; $i++) {
    $keepRunning = frankenphp_handle_request($handler);

    gc_collect_cycles();

    if (function_exists('gc_mem_caches')) {
        gc_mem_caches();
    }

    if (! $keepRunning) {
        break;
    }
}
