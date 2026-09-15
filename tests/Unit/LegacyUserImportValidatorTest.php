<?php

use App\Data\LegacyImport\LegacyUserAccount;
use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserPositionClassification;
use App\Data\LegacyImport\LegacyUserPositionProjection;
use App\Services\LegacyImport\LegacyUserImportValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;

function validationAccount(array $attributes = []): LegacyUserAccount
{
    $attributes = array_merge([
        'id' => 10,
        'nik' => '3201010101010010',
        'nip' => '198001012000011010',
        'name' => 'Validation User',
        'email' => 'validation@example.test',
        'passwordHash' => 'legacy-password-hash',
        'sourceRowIds' => [10],
        'conflictingFields' => [],
        'selectionTier' => 'active',
        'hasActiveSourceRow' => true,
        'allSourceRowsDeleted' => false,
        'sourceCreatedAt' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'sourceUpdatedAt' => CarbonImmutable::parse('2026-01-02 10:00:00'),
    ], $attributes);

    return new LegacyUserAccount(...$attributes);
}

function validationPosition(array $attributes = []): LegacyUserPositionProjection
{
    $attributes = array_merge([
        'id' => 10,
        'userId' => 10,
        'jabatanId' => 8,
        'instansiId' => 1,
        'unitKerjaId' => 18,
        'isActive' => true,
        'isCanonical' => true,
        'canonicalUserPositionId' => null,
        'legacyDuplicateReason' => null,
        'sourceStatus' => 1,
        'wasSourceActive' => true,
        'sourceCreatedAt' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'sourceUpdatedAt' => CarbonImmutable::parse('2026-01-02 10:00:00'),
        'originalDeletedAt' => null,
        'resultDeletedAt' => null,
        'endedAt' => null,
        'deactivatedAt' => null,
        'deactivationReason' => null,
        'softDeleteSynthesized' => false,
    ], $attributes);

    return new LegacyUserPositionProjection(...$attributes);
}

function projectionValidator(): LegacyUserImportValidator
{
    return new LegacyUserImportValidator(
        Mockery::mock(DatabaseManager::class),
        'testing-fingerprint-key',
    );
}

it('accepts a consistent account and position projection', function () {
    $validation = projectionValidator()->validateProjection(
        new LegacyUserAccountAggregation(
            accounts: [validationAccount()],
            invalidNikRowIds: [],
        ),
        new LegacyUserPositionClassification(
            positions: [validationPosition()],
            unclassifiableRowIds: [],
            missingReconciliationTimestampRowIds: [],
        ),
    );

    expect($validation->passed())->toBeTrue()
        ->and($validation->stage)->toBe('projection')
        ->and($validation->toArray()['read_only'])->toBeTrue()
        ->and(collect($validation->checks)->where('passed', false))->toBeEmpty();
});

it('rejects duplicate account identity and malformed alias projection', function () {
    $validation = projectionValidator()->validateProjection(
        new LegacyUserAccountAggregation(
            accounts: [
                validationAccount(),
                validationAccount([
                    'id' => 20,
                    'nik' => '3201010101010020',
                    'sourceRowIds' => [20],
                ]),
            ],
            invalidNikRowIds: [],
        ),
        new LegacyUserPositionClassification(
            positions: [
                validationPosition(),
                validationPosition([
                    'id' => 20,
                    'userId' => 20,
                    'isCanonical' => false,
                    'canonicalUserPositionId' => 10,
                    'legacyDuplicateReason' => null,
                    'resultDeletedAt' => null,
                ]),
            ],
            unclassifiableRowIds: [],
            missingReconciliationTimestampRowIds: [],
        ),
    );
    $failedChecks = collect($validation->checks)
        ->where('passed', false)
        ->pluck('name')
        ->all();

    expect($validation->passed())->toBeFalse()
        ->and($failedChecks)->toContain('projection.duplicate_account_emails')
        ->and($failedChecks)->toContain('projection.invalid_alias_shapes')
        ->and($failedChecks)->toContain('projection.aliases_without_valid_target')
        ->and($validation->metrics['projection']['duplicate_account_email_fingerprints'])
        ->toHaveCount(1)
        ->and($validation->metrics['projection']['invalid_alias_position_ids'])->toBe([20]);
});
