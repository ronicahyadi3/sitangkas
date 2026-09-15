<?php

use App\Models\UserPosition;
use App\Services\User\PositionIdentityResolver;
use Illuminate\Database\Eloquent\Builder;

function identityPosition(array $attributes): UserPosition
{
    $position = new UserPosition;
    $position->forceFill(array_merge([
        'user_id' => 900,
        'jabatan_id' => 8,
        'instansi_id' => 7,
        'unit_kerja_id' => 111,
        'is_active' => false,
        'is_canonical' => false,
        'canonical_user_position_id' => null,
    ], $attributes));
    $position->exists = true;

    return $position;
}

it('resolves aliases to one canonical identity and all equivalent IDs', function () {
    $canonical = identityPosition([
        'id' => 1357,
        'is_active' => true,
        'is_canonical' => true,
    ]);
    $alias = identityPosition([
        'id' => 245,
        'canonical_user_position_id' => 1357,
    ]);
    $canonical->setRelation('aliases', collect([$alias]));
    $alias->setRelation('canonicalPosition', $canonical);
    $resolver = new PositionIdentityResolver;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('document.uploaded_by', [245, 1357])
        ->andReturnSelf();

    expect($resolver->canonicalPosition($alias))->toBe($canonical)
        ->and($resolver->canonicalId($alias))->toBe(1357)
        ->and($resolver->canonicalId($canonical))->toBe(1357)
        ->and($resolver->equivalentIds($alias))->toBe([245, 1357])
        ->and($resolver->contains($canonical, 245))->toBeTrue()
        ->and($resolver->contains($canonical, 999))->toBeFalse()
        ->and($resolver->areEquivalent($alias, $canonical))->toBeTrue()
        ->and($resolver->whereEquivalent($query, 'document.uploaded_by', $canonical))->toBe($query);
});

it('rejects an alias whose authorization context differs from canonical', function () {
    $canonical = identityPosition([
        'id' => 225,
        'is_canonical' => true,
    ]);
    $alias = identityPosition([
        'id' => 149,
        'instansi_id' => 8,
        'canonical_user_position_id' => 225,
    ]);
    $alias->setRelation('canonicalPosition', $canonical);

    expect(fn () => (new PositionIdentityResolver)->canonicalId($alias))
        ->toThrow(LogicException::class, 'different instansi_id');
});

it('resolves the selected pptk and bud positions from an acting context', function () {
    $adminContext = identityPosition([
        'id' => 1,
        'user_id' => 1,
        'jabatan_id' => 1,
        'instansi_id' => 1,
        'unit_kerja_id' => 1,
        'is_canonical' => true,
    ]);
    $pptkPosition = identityPosition([
        'id' => 1357,
        'is_canonical' => true,
    ]);
    $budPosition = identityPosition([
        'id' => 1400,
        'jabatan_id' => 2,
        'is_canonical' => true,
    ]);
    $adminContext->setRelation('actingPptkUserPosition', $pptkPosition);
    $adminContext->setRelation('actingBudUserPosition', $budPosition);
    $resolver = new PositionIdentityResolver;

    expect($resolver->pptkActorPosition($adminContext))->toBe($pptkPosition)
        ->and($resolver->budActorPosition($adminContext))->toBe($budPosition)
        ->and($resolver->pptkActorPosition($pptkPosition))->toBe($pptkPosition);
});

it('rejects invalid position IDs and query columns', function () {
    $canonical = identityPosition([
        'id' => 225,
        'is_canonical' => true,
    ]);
    $canonical->setRelation('aliases', collect());
    $resolver = new PositionIdentityResolver;

    expect(fn () => $resolver->canonicalId(0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $resolver->whereEquivalent(
            Mockery::mock(Builder::class),
            'document.uploaded_by; DROP TABLE users',
            $canonical,
        ))->toThrow(InvalidArgumentException::class);
});
