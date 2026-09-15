<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyOrganizationResolution;
use App\Data\LegacyImport\LegacyUserRow;
use Illuminate\Database\DatabaseManager;

final class LegacyOrganizationResolver
{
    /**
     * @var array{jabatan: array<int, array{is_active: bool, deleted: bool}>, instansi: array<int, array{is_active: bool, deleted: bool}>, unit_kerja: array<int, array{instansi_id: int, is_active: bool, deleted: bool}>}|null
     */
    private ?array $masters = null;

    public function __construct(
        private DatabaseManager $database,
    ) {}

    public function resolve(LegacyUserRow $row): LegacyOrganizationResolution
    {
        $masters = $this->masters();
        $missingReferences = [];
        $inactiveReferences = [];

        $this->inspectReference('jabatan', $row->jabatanId, $masters, $missingReferences, $inactiveReferences);
        $this->inspectReference('instansi', $row->instansiId, $masters, $missingReferences, $inactiveReferences);
        $this->inspectReference('unit_kerja', $row->unitKerjaId, $masters, $missingReferences, $inactiveReferences);

        if ($row->unitKerjaId === null || ! isset($masters['unit_kerja'][$row->unitKerjaId])) {
            return new LegacyOrganizationResolution(
                canonicalInstansiId: $row->instansiId,
                missingReferences: $missingReferences,
                inactiveReferences: $inactiveReferences,
                allowedCorrection: null,
                unexpectedMismatch: null,
            );
        }

        $targetInstansiId = $masters['unit_kerja'][$row->unitKerjaId]['instansi_id'];

        if ($row->instansiId === $targetInstansiId) {
            return new LegacyOrganizationResolution(
                canonicalInstansiId: $targetInstansiId,
                missingReferences: $missingReferences,
                inactiveReferences: $inactiveReferences,
                allowedCorrection: null,
                unexpectedMismatch: null,
            );
        }

        $mismatch = [
            'row_id' => $row->id,
            'unit_kerja_id' => $row->unitKerjaId,
            'source_instansi_id' => $row->instansiId,
            'target_instansi_id' => $targetInstansiId,
        ];
        $isAllowed = $this->isAllowedCorrection(
            $row->unitKerjaId,
            $row->instansiId,
            $targetInstansiId,
        );

        return new LegacyOrganizationResolution(
            canonicalInstansiId: $targetInstansiId,
            missingReferences: $missingReferences,
            inactiveReferences: $inactiveReferences,
            allowedCorrection: $isAllowed ? $mismatch : null,
            unexpectedMismatch: $isAllowed ? null : $mismatch,
        );
    }

    /**
     * @param  array{jabatan: array<int, array{is_active: bool, deleted: bool}>, instansi: array<int, array{is_active: bool, deleted: bool}>, unit_kerja: array<int, array{instansi_id: int, is_active: bool, deleted: bool}>}  $masters
     * @param  list<array{type: string, reference_id: int|null}>  $missingReferences
     * @param  list<array{type: string, reference_id: int}>  $inactiveReferences
     */
    private function inspectReference(
        string $type,
        ?int $referenceId,
        array $masters,
        array &$missingReferences,
        array &$inactiveReferences,
    ): void {
        if ($referenceId === null || ! isset($masters[$type][$referenceId])) {
            $missingReferences[] = [
                'type' => $type,
                'reference_id' => $referenceId,
            ];

            return;
        }

        $reference = $masters[$type][$referenceId];

        if (! $reference['is_active'] || $reference['deleted']) {
            $inactiveReferences[] = [
                'type' => $type,
                'reference_id' => $referenceId,
            ];
        }
    }

    private function isAllowedCorrection(
        int $unitKerjaId,
        ?int $sourceInstansiId,
        int $targetInstansiId,
    ): bool {
        $allowlist = config("legacy_import.organization.allowed_instansi_corrections.{$unitKerjaId}");

        return is_array($allowlist)
            && (int) ($allowlist['source_instansi_id'] ?? 0) === $sourceInstansiId
            && (int) ($allowlist['target_instansi_id'] ?? 0) === $targetInstansiId;
    }

    /**
     * @return array{jabatan: array<int, array{is_active: bool, deleted: bool}>, instansi: array<int, array{is_active: bool, deleted: bool}>, unit_kerja: array<int, array{instansi_id: int, is_active: bool, deleted: bool}>}
     */
    private function masters(): array
    {
        if ($this->masters !== null) {
            return $this->masters;
        }

        $connection = $this->database->connection();
        $jabatans = $connection->table('jabatans')
            ->get(['id', 'is_active', 'deleted_at'])
            ->mapWithKeys(static fn (object $row): array => [
                (int) $row->id => [
                    'is_active' => (bool) $row->is_active,
                    'deleted' => $row->deleted_at !== null,
                ],
            ])->all();
        $instansis = $connection->table('instansis')
            ->get(['id', 'is_active', 'deleted_at'])
            ->mapWithKeys(static fn (object $row): array => [
                (int) $row->id => [
                    'is_active' => (bool) $row->is_active,
                    'deleted' => $row->deleted_at !== null,
                ],
            ])->all();
        $unitKerjas = $connection->table('unit_kerjas')
            ->get(['id', 'instansi_id', 'is_active', 'deleted_at'])
            ->mapWithKeys(static fn (object $row): array => [
                (int) $row->id => [
                    'instansi_id' => (int) $row->instansi_id,
                    'is_active' => (bool) $row->is_active,
                    'deleted' => $row->deleted_at !== null,
                ],
            ])->all();

        return $this->masters = [
            'jabatan' => $jabatans,
            'instansi' => $instansis,
            'unit_kerja' => $unitKerjas,
        ];
    }
}
