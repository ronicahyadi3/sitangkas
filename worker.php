<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Once;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

require __DIR__.'/vendor/autoload.php';

if (! function_exists('frankenphp_handle_request')) {
    fwrite(STDERR, "This worker must be run by FrankenPHP.\n");
    exit(1);
}

$app = require __DIR__.'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);

/*
|--------------------------------------------------------------------------
| Worker lifecycle
|--------------------------------------------------------------------------
|
| MAX_REQUESTS
|   Worker direcycle setelah sejumlah request untuk membatasi akumulasi
|   state/memory pada aplikasi Laravel long-running.
|
| WORKER_MEMORY_LIMIT_MB
|   Guard tambahan. Jika memory worker setelah sebuah request melewati
|   ambang ini, loop berakhir dan FrankenPHP akan memulai worker baru.
|   Nilai 0 = dinonaktifkan.
|
| GC_MEM_CACHES_EVERY
|   gc_collect_cycles() tetap dijalankan setiap request. gc_mem_caches()
|   yang lebih agresif dijalankan berkala agar tidak terlalu membebani
|   throughput.
|
*/
$maxRequests = max(1, (int) ($_SERVER['MAX_REQUESTS'] ?? 500));
$workerMemoryLimitMb = max(0, (int) ($_SERVER['WORKER_MEMORY_LIMIT_MB'] ?? 0));
$workerMemoryLimitBytes = $workerMemoryLimitMb > 0
    ? $workerMemoryLimitMb * 1024 * 1024
    : 0;
$gcMemCachesEvery = max(1, (int) ($_SERVER['GC_MEM_CACHES_EVERY'] ?? 25));

$handler = static function () use ($app, $kernel): void {
    /*
     * Reset locale/timezone dasar sebelum request berikutnya.
     * Middleware aplikasi tetap dapat menggantinya untuk user/request tertentu.
     */
    try {
        if ($app->bound('config')) {
            $config = $app->make('config');

            $locale = $config->get('app.locale');
            if (is_string($locale) && $locale !== '') {
                $app->setLocale($locale);
            }

            $timezone = $config->get('app.timezone');
            if (is_string($timezone) && $timezone !== '') {
                date_default_timezone_set($timezone);
            }
        }
    } catch (Throwable $e) {
        // Jangan matikan worker hanya karena cleanup/reset non-kritis gagal.
    }

    $request = Request::capture();
    $response = null;

    try {
        $response = $kernel->handle($request);
    } catch (Throwable $e) {
        try {
            $app->make(ExceptionHandler::class)->report($e);
        } catch (Throwable $reportingException) {
            error_log((string) $e);
            error_log((string) $reportingException);
        }

        $debug = false;

        try {
            $debug = (bool) $app->make('config')->get('app.debug', false);
        } catch (Throwable $ignored) {
        }

        $response = new Response(
            $debug ? (string) $e : 'Internal Server Error',
            500,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    try {
        $response->send();
    } finally {
        /*
         * Jalankan terminable middleware.
         */
        try {
            $kernel->terminate($request, $response);
        } catch (Throwable $e) {
            try {
                $app->make(ExceptionHandler::class)->report($e);
            } catch (Throwable $ignored) {
                error_log((string) $e);
            }
        }

        /*
         * AuthManager menyimpan guard yang sudah di-resolve.
         * Pada worker persisten guard tersebut harus dilupakan agar state
         * autentikasi user sebelumnya tidak ikut ke request berikutnya.
         */
        try {
            if ($app->bound('auth')) {
                $auth = $app->make('auth');

                if (method_exists($auth, 'forgetGuards')) {
                    $auth->forgetGuards();
                }
            }
        } catch (Throwable $ignored) {
        }

        /*
         * Laravel versi baru memiliki cache once() yang bersifat static.
         * Flush secara kondisional agar file ini tetap kompatibel dengan
         * Laravel yang lebih lama.
         */
        if (
            class_exists(Once::class)
            && method_exists(Once::class, 'flush')
        ) {
            Once::flush();
        }

        /*
         * Bersihkan instance yang bersifat request/scoped serta cache Facade.
         * Singleton aplikasi yang memang aman untuk dipakai ulang tetap hidup,
         * sehingga keuntungan bootstrap Worker Mode tetap dipertahankan.
         */
        if (method_exists($app, 'forgetScopedInstances')) {
            $app->forgetScopedInstances();
        }

        $app->forgetInstance('request');

        Facade::clearResolvedInstances();

        unset($request, $response);
    }
};

for ($requestCount = 0; $requestCount < $maxRequests; $requestCount++) {
    $keepRunning = frankenphp_handle_request($handler);

    /*
     * Official worker pattern merekomendasikan garbage collection setelah
     * request. gc_mem_caches() dibuat berkala untuk menekan overhead.
     */
    gc_collect_cycles();

    if (
        function_exists('gc_mem_caches')
        && (($requestCount + 1) % $gcMemCachesEvery === 0)
    ) {
        gc_mem_caches();
    }

    /*
     * Recycle lebih awal jika worker telah membesar melewati guard memory.
     */
    if (
        $workerMemoryLimitBytes > 0
        && memory_get_usage(true) >= $workerMemoryLimitBytes
    ) {
        break;
    }

    if (! $keepRunning) {
        break;
    }
}

/*
 * Cleanup terakhir sebelum worker keluar/recycle.
 */
gc_collect_cycles();

if (function_exists('gc_mem_caches')) {
    gc_mem_caches();
}
