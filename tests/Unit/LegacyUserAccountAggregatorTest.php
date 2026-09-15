<?php

use App\Data\LegacyImport\LegacyUserRow;
use App\Services\LegacyImport\LegacyUserAccountAggregator;
use Carbon\CarbonImmutable;

function legacyUserRow(array $attributes = []): LegacyUserRow
{
    $attributes = array_merge([
        'id' => 1,
        'name' => 'Default User',
        'status' => 1,
        'nip' => '198001012000011001',
        'nik' => '3201010101010001',
        'jabatanId' => 1,
        'instansiId' => 1,
        'unitKerjaId' => 1,
        'fileSk' => '',
        'email' => 'default@example.test',
        'passwordHash' => 'password-hash',
        'createdAt' => CarbonImmutable::parse('2026-01-01 00:00:00'),
        'updatedAt' => CarbonImmutable::parse('2026-01-01 00:00:00'),
        'deletedAt' => null,
    ], $attributes);

    return new LegacyUserRow(...$attributes);
}

it('selects one canonical account per NIK deterministically', function () {
    $olderActive = legacyUserRow([
        'id' => 10,
        'name' => 'Older Active',
        'email' => 'older@example.test',
        'createdAt' => CarbonImmutable::parse('2025-01-01 00:00:00'),
    ]);
    $winningActive = legacyUserRow([
        'id' => 20,
        'name' => '  Winning   Active  ',
        'nip' => ' 198001012000011002 ',
        'email' => ' WINNER@EXAMPLE.TEST ',
        'passwordHash' => 'winning-hash',
        'createdAt' => CarbonImmutable::parse('2025-01-01 00:00:00'),
    ]);
    $newerInactive = legacyUserRow([
        'id' => 30,
        'name' => 'Newer Inactive',
        'status' => 0,
        'createdAt' => CarbonImmutable::parse('2026-01-01 00:00:00'),
    ]);

    $aggregation = (new LegacyUserAccountAggregator)->aggregate([
        $newerInactive,
        $winningActive,
        $olderActive,
    ]);

    expect($aggregation->accounts)->toHaveCount(1)
        ->and($aggregation->invalidNikRowIds)->toBe([])
        ->and($aggregation->accounts[0]->id)->toBe(20)
        ->and($aggregation->accounts[0]->name)->toBe('Winning Active')
        ->and($aggregation->accounts[0]->nip)->toBe('198001012000011002')
        ->and($aggregation->accounts[0]->email)->toBe('winner@example.test')
        ->and($aggregation->accounts[0]->passwordHash())->toBe('winning-hash')
        ->and($aggregation->accounts[0]->sourceRowIds)->toBe([10, 20, 30])
        ->and($aggregation->accounts[0]->conflictingFields)->toBe([
            'name',
            'nip',
            'email',
            'password',
        ])
        ->and($aggregation->accounts[0]->selectionTier)->toBe('active')
        ->and($aggregation->accounts[0]->hasActiveSourceRow)->toBeTrue()
        ->and($aggregation->accounts[0]->allSourceRowsDeleted)->toBeFalse();
});

it('falls back to the latest historical row and reports invalid NIK rows', function () {
    $olderDeleted = legacyUserRow([
        'id' => 40,
        'nik' => '3201010101010002',
        'status' => 0,
        'createdAt' => CarbonImmutable::parse('2024-01-01 00:00:00'),
        'deletedAt' => CarbonImmutable::parse('2025-01-01 00:00:00'),
    ]);
    $newerDeleted = legacyUserRow([
        'id' => 50,
        'nik' => '3201010101010002',
        'status' => 0,
        'createdAt' => CarbonImmutable::parse('2025-01-01 00:00:00'),
        'deletedAt' => CarbonImmutable::parse('2026-01-01 00:00:00'),
    ]);
    $invalidNik = legacyUserRow([
        'id' => 60,
        'nik' => 'invalid',
    ]);

    $aggregation = (new LegacyUserAccountAggregator)->aggregate([
        $newerDeleted,
        $invalidNik,
        $olderDeleted,
    ]);

    expect($aggregation->accounts)->toHaveCount(1)
        ->and($aggregation->accounts[0]->id)->toBe(50)
        ->and($aggregation->accounts[0]->selectionTier)->toBe('deleted_history')
        ->and($aggregation->accounts[0]->hasActiveSourceRow)->toBeFalse()
        ->and($aggregation->accounts[0]->allSourceRowsDeleted)->toBeTrue()
        ->and($aggregation->invalidNikRowIds)->toBe([60]);
});
