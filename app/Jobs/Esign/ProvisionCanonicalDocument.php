<?php

declare(strict_types=1);

namespace App\Jobs\Esign;

use App\Actions\Esign\ProvisionCanonicalDocument as ProvisionCanonicalDocumentAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionCanonicalDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public readonly int $documentId,
        public readonly ?int $actorUserId = null,
        public readonly ?int $actorUserPositionId = null,
        public readonly bool $actorIsActing = false,
    ) {
        $this->timeout = (int) config('esign.processing.provisioning_job_timeout_seconds', 180);
        $this->uniqueFor = $this->timeout + 600;
        $this->onConnection((string) config('esign.processing.queue_connection', 'signatures'));
        $this->onQueue((string) config('esign.processing.queue', 'signatures'));
    }

    public function handle(ProvisionCanonicalDocumentAction $action): void
    {
        $action->handle(
            documentId: $this->documentId,
            actorUserId: $this->actorUserId,
            actorUserPositionId: $this->actorUserPositionId,
            actorIsActing: $this->actorIsActing,
        );
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 180, 300];
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("canonical-document:{$this->documentId}"))
                ->releaseAfter(15)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('module_esign')->critical('Provisioning canonical dokumen berhenti tidak normal.', [
            'document_id' => $this->documentId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
