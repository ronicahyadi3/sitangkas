<?php

namespace App\Jobs\Esign;

use App\Actions\Esign\PerformEsignAttemptAction;
use App\Services\Esign\EphemeralSigningSecretStore;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class PerformEsignAttempt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const SUPPORTS_VISIBLE_MULTI_OPERATION = true;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public readonly int $attemptId,
        public readonly string $secretReference,
    ) {
        $this->timeout = (int) config('esign.processing.job_timeout_seconds', 900);
        $this->uniqueFor = $this->timeout + 120;
        $this->onConnection((string) config('esign.processing.queue_connection', 'signatures'));
        $this->onQueue((string) config('esign.processing.queue', 'signatures'));
    }

    public function handle(PerformEsignAttemptAction $action): void
    {
        $action->handle($this->attemptId, $this->secretReference);
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("esign-attempt:{$this->attemptId}"))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function failed(?Throwable $exception): void
    {
        try {
            app(EphemeralSigningSecretStore::class)->forget($this->secretReference);
        } catch (Throwable) {
        }

        Log::channel('module_esign')->critical('Job attempt TTE berhenti tidak normal.', [
            'attempt_id' => $this->attemptId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
