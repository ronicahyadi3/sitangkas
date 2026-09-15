<?php

use App\Data\LegacyImport\LegacyOrganizationResolution;
use App\Data\LegacyImport\LegacyUserRow;
use App\Services\LegacyImport\LegacyUserAccountAggregator;
use App\Services\LegacyImport\LegacyUserPositionClassifier;
use Carbon\CarbonImmutable;

function positionLegacyUserRow(array $attributes = []): LegacyUserRow
{
    static $nextId = 1;
    $id = $attributes['id'] ?? $nextId++;
    $nikSuffix = str_pad((string) $id, 4, '0', STR_PAD_LEFT);

    $attributes = array_merge([
        'id' => $id,
        'name' => 'Position User',
        'status' => 1,
        'nip' => '198001012000011001',
        'nik' => '320101010101'.$nikSuffix,
        'jabatanId' => 8,
        'instansiId' => 1,
        'unitKerjaId' => 18,
        'fileSk' => '',
        'email' => 'position-'.$id.'@example.test',
        'passwordHash' => 'password-hash',
        'createdAt' => CarbonImmutable::parse('2026-01-01 00:00:00'),
        'updatedAt' => CarbonImmutable::parse('2026-01-01 00:00:00'),
        'deletedAt' => null,
    ], $attributes);

    return new LegacyUserRow(...$attributes);
}

function importableOrganization(int $canonicalInstansiId = 1): LegacyOrganizationResolution
{
    return new LegacyOrganizationResolution(
        canonicalInstansiId: $canonicalInstansiId,
        missingReferences: [],
        inactiveReferences: [],
        allowedCorrection: null,
        unexpectedMismatch: null,
    );
}

it('classifies canonical and alias positions deterministically', function () {
    $olderActive = positionLegacyUserRow([
        'id' => 1364,
        'nik' => '3573031706740003',
        'createdAt' => CarbonImmutable::parse('2025-05-03 11:32:55'),
    ]);
    $newerActive = positionLegacyUserRow([
        'id' => 1367,
        'nik' => '3573031706740003',
        'createdAt' => CarbonImmutable::parse('2025-05-06 10:13:37'),
    ]);
    $uniqueInactive = positionLegacyUserRow([
        'id' => 1400,
        'nik' => '3573031706740004',
        'status' => 0,
    ]);
    $rows = [$uniqueInactive, $newerActive, $olderActive];
    $accounts = (new LegacyUserAccountAggregator)->aggregate($rows);
    $organizations = [
        1364 => importableOrganization(7),
        1367 => importableOrganization(7),
        1400 => importableOrganization(7),
    ];

    $classification = (new LegacyUserPositionClassifier)->classify(
        $rows,
        $accounts,
        $organizations,
    );
    $positions = collect($classification->positions)->keyBy('id');

    expect($classification->positions)->toHaveCount(3)
        ->and($classification->unclassifiableRowIds)->toBe([])
        ->and($classification->missingReconciliationTimestampRowIds)->toBe([])
        ->and($positions[1367]->isCanonical)->toBeTrue()
        ->and($positions[1367]->isActive)->toBeTrue()
        ->and($positions[1367]->canonicalUserPositionId)->toBeNull()
        ->and($positions[1367]->instansiId)->toBe(7)
        ->and($positions[1364]->isCanonical)->toBeFalse()
        ->and($positions[1364]->isActive)->toBeFalse()
        ->and($positions[1364]->canonicalUserPositionId)->toBe(1367)
        ->and($positions[1364]->softDeleteSynthesized)->toBeTrue()
        ->and($positions[1364]->resultDeletedAt?->toDateTimeString())->toBe('2025-05-06 10:13:37')
        ->and($positions[1364]->deactivationReason)->toBe('superseded_by_newer_legacy_position')
        ->and($positions[1400]->isCanonical)->toBeTrue()
        ->and($positions[1400]->isActive)->toBeFalse();
});

it('preserves legacy soft deletes and reports rows without a valid context', function () {
    $olderDeleted = positionLegacyUserRow([
        'id' => 230,
        'nik' => '3573035305870004',
        'createdAt' => CarbonImmutable::parse('2024-10-01 10:10:56'),
        'deletedAt' => CarbonImmutable::parse('2024-10-01 10:11:50'),
    ]);
    $newerDeleted = positionLegacyUserRow([
        'id' => 231,
        'nik' => '3573035305870004',
        'createdAt' => CarbonImmutable::parse('2024-10-01 10:11:03'),
        'deletedAt' => CarbonImmutable::parse('2024-10-01 10:11:46'),
    ]);
    $invalidOrganization = positionLegacyUserRow([
        'id' => 300,
        'nik' => '3573035305870005',
    ]);
    $rows = [$invalidOrganization, $newerDeleted, $olderDeleted];
    $accounts = (new LegacyUserAccountAggregator)->aggregate($rows);
    $organizations = [
        230 => importableOrganization(),
        231 => importableOrganization(),
        300 => new LegacyOrganizationResolution(
            canonicalInstansiId: 2,
            missingReferences: [],
            inactiveReferences: [],
            allowedCorrection: null,
            unexpectedMismatch: [
                'row_id' => 300,
                'unit_kerja_id' => 18,
                'source_instansi_id' => 1,
                'target_instansi_id' => 2,
            ],
        ),
    ];

    $classification = (new LegacyUserPositionClassifier)->classify(
        $rows,
        $accounts,
        $organizations,
    );
    $positions = collect($classification->positions)->keyBy('id');

    expect($classification->positions)->toHaveCount(2)
        ->and($classification->unclassifiableRowIds)->toBe([300])
        ->and($positions[231]->isCanonical)->toBeTrue()
        ->and($positions[231]->isActive)->toBeFalse()
        ->and($positions[230]->isCanonical)->toBeFalse()
        ->and($positions[230]->canonicalUserPositionId)->toBe(231)
        ->and($positions[230]->originalDeletedAt?->toDateTimeString())->toBe('2024-10-01 10:11:50')
        ->and($positions[230]->resultDeletedAt?->toDateTimeString())->toBe('2024-10-01 10:11:50')
        ->and($positions[230]->softDeleteSynthesized)->toBeFalse();
});
