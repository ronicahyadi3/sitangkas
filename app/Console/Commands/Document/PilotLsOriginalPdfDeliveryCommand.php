<?php

declare(strict_types=1);

namespace App\Console\Commands\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailSourceState;
use App\Enums\Document\PdfDeliveryMode;
use App\Models\Document;
use App\Services\Document\PdfDeliverySourceIntegrityInspector;
use App\Support\EncryptedId;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

#[Signature('pdf-delivery:pilot-ls-original
    {document? : Internal numeric or opaque encrypted LS SPP document identifier}
    {--resource=document : document, billing, or spj_fungsional}
    {--scan-limit=100 : Maximum recent LS SPP rows inspected when document is omitted}')]
#[Description('Run a read-only preflight for the controlled LS SPP ORIGINAL PDF delivery pilot.')]
final class PilotLsOriginalPdfDeliveryCommand extends Command
{
    public function handle(
        PdfDeliverySource $sourceResolver,
        PdfDeliverySourceIntegrityInspector $integrity,
    ): int {
        try {
            $this->assertPolicyBaseline();
            $this->assertRoutesAvailable();

            $resourceKey = $this->resourceKey();
            [$document, $source] = $this->resolvePilotSource($sourceResolver, $resourceKey);

            $integrity->assertMatches($source);

            $this->info('Preflight pilot LS SPP ORIGINAL lulus. Tidak ada file atau data yang diubah.');
            $this->table(['Pemeriksaan', 'Hasil'], [
                ['Dokumen', EncryptedId::encode((int) $document->getKey())],
                ['Tipe', (string) $document->payment_type.' / '.(string) $document->src_type],
                ['Resource', $resourceKey],
                ['Delivery mode', PdfDeliveryMode::Original->value],
                ['Source state', $source->sourceState->value],
                ['Artifact canonical', $source->artifactPublicId ?? 'legacy transition'],
                ['Ukuran', number_format($source->sizeBytes, 0, ',', '.').' byte'],
                ['Integritas byte', 'sesuai metadata SHA-256'],
                ['Posisi wajib watermark', '0'],
            ]);

            $this->newLine();
            $this->warn('Gate berikutnya tetap acceptance manual memakai user/posisi nyata.');
            $this->line('Buka LS SPP > Detail Dokumen, lalu periksa viewer, validasi, download, dan penolakan lintas scope.');

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (Throwable $exception) {
            $this->error('Preflight pilot gagal secara aman: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertPolicyBaseline(): void
    {
        if (! Schema::hasColumn('user_positions', 'pdf_watermark_required')) {
            throw new InvalidArgumentException(
                'Kolom user_positions.pdf_watermark_required belum tersedia. Jalankan migration additive R9 lebih dahulu.',
            );
        }

        $watermarkRequiredPositions = DB::table('user_positions')
            ->where('pdf_watermark_required', true)
            ->count();

        if ($watermarkRequiredPositions !== 0) {
            throw new InvalidArgumentException(
                "Pilot ORIGINAL mensyaratkan seluruh posisi masih false; ditemukan {$watermarkRequiredPositions} posisi true.",
            );
        }
    }

    private function assertRoutesAvailable(): void
    {
        foreach ([
            'document.pdf.viewer',
            'document.pdf.content',
            'document.pdf.download',
        ] as $routeName) {
            if (! Route::has($routeName)) {
                throw new InvalidArgumentException("Route {$routeName} belum tersedia.");
            }
        }
    }

    private function resourceKey(): string
    {
        $resourceKey = trim((string) $this->option('resource'));

        if (! in_array($resourceKey, [
            PdfDeliverySource::RESOURCE_DOCUMENT,
            PdfDeliverySource::RESOURCE_BILLING,
            PdfDeliverySource::RESOURCE_SPJ_FUNCTIONAL,
        ], true)) {
            throw new InvalidArgumentException('Resource pilot harus document, billing, atau spj_fungsional.');
        }

        return $resourceKey;
    }

    /** @return array{Document, ResolvedPdfDeliverySource} */
    private function resolvePilotSource(
        PdfDeliverySource $sourceResolver,
        string $resourceKey,
    ): array {
        $argument = trim((string) ($this->argument('document') ?? ''));

        if ($argument !== '') {
            $document = $this->findDocument($argument);
            $this->assertLsSpp($document);
            $source = $sourceResolver->resolve($document, $resourceKey);

            if (! $source instanceof ResolvedPdfDeliverySource) {
                throw new InvalidArgumentException('Source PDF untuk dokumen pilot tidak tersedia.');
            }

            $this->assertCanonicalSource($source);

            return [$document, $source];
        }

        $scanLimit = filter_var(
            $this->option('scan-limit'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 500]],
        );

        if (! is_int($scanLimit)) {
            throw new InvalidArgumentException('scan-limit harus bernilai 1 sampai 500.');
        }

        /** @var Collection<int, Document> $documents */
        $documents = Document::query()
            ->with('pdfDeliveryArtifacts')
            ->where('payment_type', 'LS')
            ->where('src_type', Document::TYPE_SPP)
            ->whereHas('pdfDeliveryArtifacts')
            ->latest('id')
            ->limit($scanLimit)
            ->get();

        foreach ($documents as $document) {
            $source = $sourceResolver->resolve($document, $resourceKey);
            if ($source instanceof ResolvedPdfDeliverySource
                && $source->sourceState === DocumentDetailSourceState::Canonical) {
                return [$document, $source];
            }
        }

        throw new InvalidArgumentException(
            "Tidak ada source LS SPP yang dapat diselesaikan pada {$scanLimit} dokumen terbaru.",
        );
    }

    private function findDocument(string $identifier): Document
    {
        $documentId = ctype_digit($identifier)
            ? (int) $identifier
            : EncryptedId::tryDecode($identifier);

        if (! is_int($documentId) || $documentId < 1) {
            throw new InvalidArgumentException('Identifier dokumen pilot tidak valid.');
        }

        return Document::query()
            ->with('pdfDeliveryArtifacts')
            ->findOrFail($documentId);
    }

    private function assertLsSpp(Document $document): void
    {
        if ((string) $document->payment_type !== 'LS'
            || (string) $document->src_type !== Document::TYPE_SPP) {
            throw new InvalidArgumentException('Pilot R9 hanya menerima dokumen LS SPP.');
        }
    }

    private function assertCanonicalSource(ResolvedPdfDeliverySource $source): void
    {
        if ($source->sourceState !== DocumentDetailSourceState::Canonical
            || $source->documentArtifactId === null
            || $source->artifactPublicId === null) {
            throw new InvalidArgumentException(
                'Pilot R9 mensyaratkan source artifact canonical; fallback legacy belum boleh menjadi bukti pilot.',
            );
        }
    }
}
