<?php

namespace App\Services\Auth;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CurrentUserContext
{
    /**
     * Effective UserPosition used by dashboards, navbar, middleware, and modules.
     *
     * @var string
     */
    public const ACTIVE_USER_POSITION_ATTRIBUTE = 'active_user_position';

    /**
     * Real UserPosition selected from login context.
     *
     * @var string
     */
    public const REAL_ACTIVE_USER_POSITION_ATTRIBUTE = 'real_active_user_position';

    /**
     * @var string
     */
    public const IS_ACTING_CONTEXT_ATTRIBUTE = 'is_acting_context';

    public const ACTIVE_USER_POSITION_SESSION_KEY = 'active_user_position_id';

    public const ACTIVE_YEAR_ATTRIBUTE = 'active_year';

    public const ACTIVE_YEAR_SESSION_KEY = 'tahun_aktif';

    public function __construct(private AdminSuperPositionScope $adminSuperPositionScope) {}

    public function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    public function activeUserPositionId(Request $request): int|string|null
    {
        return $request->session()->get(self::ACTIVE_USER_POSITION_SESSION_KEY);
    }

    public function activeYear(Request $request): ?int
    {
        $activeYear = $request->attributes->get(self::ACTIVE_YEAR_ATTRIBUTE)
            ?? $request->session()->get(self::ACTIVE_YEAR_SESSION_KEY);

        if (! filled($activeYear)) {
            return null;
        }

        return (int) $activeYear;
    }

    public function hasSessionContext(Request $request): bool
    {
        return filled($this->activeUserPositionId($request))
            && filled($request->session()->get(self::ACTIVE_YEAR_SESSION_KEY));
    }

    public function activePosition(Request $request): ?UserPosition
    {
        $activeUserPosition = $request->attributes->get(self::ACTIVE_USER_POSITION_ATTRIBUTE);

        if ($activeUserPosition instanceof UserPosition) {
            return $activeUserPosition;
        }

        $realActiveUserPosition = $this->realActivePosition($request);

        if (! $realActiveUserPosition instanceof UserPosition) {
            return null;
        }

        if (! $this->isAdminSuperPosition($realActiveUserPosition)) {
            $this->forgetAdminSuperActingContext($request);
            $this->attachToRequest($request, $realActiveUserPosition, false, $realActiveUserPosition);

            return $realActiveUserPosition;
        }

        $actingUserPosition = $this->actingPosition($request, $realActiveUserPosition);

        if ($actingUserPosition instanceof UserPosition) {
            $this->attachToRequest($request, $actingUserPosition, true, $realActiveUserPosition);

            return $actingUserPosition;
        }

        $this->attachToRequest($request, $realActiveUserPosition, false, $realActiveUserPosition);

        return $realActiveUserPosition;
    }

    public function realActivePosition(Request $request): ?UserPosition
    {
        $realActiveUserPosition = $request->attributes->get(self::REAL_ACTIVE_USER_POSITION_ATTRIBUTE);

        if ($realActiveUserPosition instanceof UserPosition) {
            return $realActiveUserPosition;
        }

        $user = $this->user($request);
        $activeUserPositionId = $this->activeUserPositionId($request);

        if (! $user instanceof User || ! filled($activeUserPositionId)) {
            return null;
        }

        $realActiveUserPosition = $this->selectablePositionsQuery($user)
            ->whereKey($activeUserPositionId)
            ->first();

        if (! $realActiveUserPosition instanceof UserPosition) {
            return null;
        }

        $this->attachRealPositionToRequest($request, $realActiveUserPosition);

        return $realActiveUserPosition;
    }

    public function selectablePosition(User $user, int|string $userPositionId): ?UserPosition
    {
        $userPosition = $this->selectablePositionsQuery($user)
            ->whereKey($userPositionId)
            ->first();

        return $userPosition instanceof UserPosition ? $userPosition : null;
    }

    /**
     * @return Collection<int, UserPosition>
     */
    public function selectablePositions(User $user): Collection
    {
        return $this->selectablePositionsQuery($user)->get();
    }

    public function hasSelectablePositions(User $user): bool
    {
        return $this->selectablePositionsQuery($user)->exists();
    }

    public function selectablePositionsQuery(User $user): Builder
    {
        return UserPosition::query()
            ->with(['jabatan', 'instansi', 'unitKerja'])
            ->forUser($user)
            ->availableForSelection()
            ->withActiveReferences()
            ->preferredFirst();
    }

    public function activatePosition(Request $request, UserPosition $userPosition, ?int $activeYear = null): void
    {
        $activeYear ??= $this->activeYear($request)
            ?? $this->user($request)?->tahun_aktif
            ?? (int) now()->year;

        $this->forgetAdminSuperActingContext($request);

        $request->session()->put(self::ACTIVE_USER_POSITION_SESSION_KEY, $userPosition->getKey());
        $request->session()->put(self::ACTIVE_YEAR_SESSION_KEY, $activeYear);

        $this->attachRealPositionToRequest($request, $userPosition);
        $this->attachToRequest($request, $userPosition, false, $userPosition);
    }

    public function forgetActivePosition(Request $request): void
    {
        $request->session()->forget(self::ACTIVE_USER_POSITION_SESSION_KEY);
        $this->forgetAdminSuperActingContext($request);
        $request->attributes->remove(self::ACTIVE_USER_POSITION_ATTRIBUTE);
        $request->attributes->remove(self::REAL_ACTIVE_USER_POSITION_ATTRIBUTE);
        $request->attributes->remove(self::IS_ACTING_CONTEXT_ATTRIBUTE);
        $request->attributes->remove('activeUserPosition');
        $request->attributes->remove('realActiveUserPosition');
    }

    public function attachToRequest(
        Request $request,
        UserPosition $userPosition,
        bool $isActingContext = false,
        ?UserPosition $realActiveUserPosition = null
    ): void {
        $request->attributes->set(self::ACTIVE_USER_POSITION_ATTRIBUTE, $userPosition);
        $request->attributes->set('activeUserPosition', $userPosition);
        $request->attributes->set(self::IS_ACTING_CONTEXT_ATTRIBUTE, $isActingContext);

        if ($realActiveUserPosition instanceof UserPosition) {
            $this->attachRealPositionToRequest($request, $realActiveUserPosition);
        }

        $request->attributes->set(self::ACTIVE_YEAR_ATTRIBUTE, $this->activeYear($request));
    }

    public function isAdminSuperPosition(UserPosition $userPosition): bool
    {
        $userPosition->loadMissing('jabatan');

        return $userPosition->jabatan instanceof Jabatan
            && $this->adminSuperPositionScope->isAdminSuperJabatan($userPosition->jabatan);
    }

    public function isRealActivePositionAdminSuper(Request $request): bool
    {
        $realActiveUserPosition = $this->realActivePosition($request);

        return $realActiveUserPosition instanceof UserPosition
            && $this->isAdminSuperPosition($realActiveUserPosition);
    }

    public function effectiveContextIsActing(Request $request): bool
    {
        if (! $request->attributes->has(self::IS_ACTING_CONTEXT_ATTRIBUTE)) {
            $this->activePosition($request);
        }

        return $request->attributes->get(self::IS_ACTING_CONTEXT_ATTRIBUTE) === true;
    }

    public function hasCompleteAdminSuperActingContext(Request $request): bool
    {
        return $this->adminSuperActingContextData($request) !== null;
    }

    /**
     * @return array{
     *     jabatan_id: int,
     *     jabatan_name: string|null,
     *     instansi_id: int,
     *     unit_kerja_id: int,
     *     pptk_user_position_id: int|null,
     *     bud_user_position_id: int|null,
     *     selected_at: mixed
     * }|null
     */
    public function adminSuperActingContextData(Request $request): ?array
    {
        $sessionKeys = $this->adminSuperActingContextSessionKeys();
        $jabatanId = $this->sessionInteger($request, $sessionKeys['jabatan_id']);
        $instansiId = $this->sessionInteger($request, $sessionKeys['instansi_id']);
        $unitKerjaId = $this->sessionInteger($request, $sessionKeys['unit_kerja_id']);

        if ($jabatanId === null || $instansiId === null || $unitKerjaId === null) {
            return null;
        }

        return [
            'jabatan_id' => $jabatanId,
            'jabatan_name' => $this->sessionString($request, $sessionKeys['jabatan_name']),
            'instansi_id' => $instansiId,
            'unit_kerja_id' => $unitKerjaId,
            'pptk_user_position_id' => $this->sessionInteger($request, $sessionKeys['pptk_user_position_id']),
            'bud_user_position_id' => $this->sessionInteger($request, $sessionKeys['bud_user_position_id']),
            'selected_at' => $request->session()->get($sessionKeys['selected_at']),
        ];
    }

    /**
     * @return array{
     *     jabatan_id: string,
     *     jabatan_name: string,
     *     instansi_id: string,
     *     unit_kerja_id: string,
     *     pptk_user_position_id: string,
     *     bud_user_position_id: string,
     *     selected_at: string
     * }
     */
    public function adminSuperActingContextSessionKeys(): array
    {
        $configuredSessionKeys = config('position_rules.manual_context.session_keys', []);
        $configuredSessionKeys = is_array($configuredSessionKeys) ? $configuredSessionKeys : [];

        $fallbackSessionKeys = [
            'jabatan_id' => 'acting_jabatan_id',
            'jabatan_name' => 'acting_jabatan_name',
            'instansi_id' => 'acting_instansi_id',
            'unit_kerja_id' => 'acting_unit_kerja_id',
            'pptk_user_position_id' => 'acting_pptk_user_position_id',
            'bud_user_position_id' => 'acting_bud_user_position_id',
            'selected_at' => 'acting_selected_at',
        ];

        foreach ($fallbackSessionKeys as $key => $fallbackSessionKey) {
            $sessionKey = $configuredSessionKeys[$key] ?? $fallbackSessionKey;
            $fallbackSessionKeys[$key] = is_string($sessionKey) && $sessionKey !== ''
                ? $sessionKey
                : $fallbackSessionKey;
        }

        return $fallbackSessionKeys;
    }

    public function forgetAdminSuperActingContext(Request $request): void
    {
        $request->session()->forget(array_values($this->adminSuperActingContextSessionKeys()));
        $request->attributes->remove(self::ACTIVE_USER_POSITION_ATTRIBUTE);
        $request->attributes->remove(self::IS_ACTING_CONTEXT_ATTRIBUTE);
        $request->attributes->remove('activeUserPosition');
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Request $request): array
    {
        return [
            'user' => $this->user($request),
            'activeUserPosition' => $this->activePosition($request),
            'realActiveUserPosition' => $this->realActivePosition($request),
            'activeUserPositionId' => $this->activeUserPositionId($request),
            'activeYear' => $this->activeYear($request),
            'isActingContext' => $this->effectiveContextIsActing($request),
            'isRealActiveUserPositionAdminSuper' => $this->isRealActivePositionAdminSuper($request),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshot(Request $request): ?array
    {
        $activeUserPosition = $this->activePosition($request);

        if (! $activeUserPosition instanceof UserPosition) {
            return null;
        }

        return [
            ...$activeUserPosition->toAuthenticationContext(),
            'tahun_aktif' => $this->activeYear($request),
            'is_acting_context' => $this->effectiveContextIsActing($request),
            'real_user_position_id' => $this->realActivePosition($request)?->getKey(),
            'acting_context' => $this->effectiveContextIsActing($request)
                ? $this->adminSuperActingContextData($request)
                : null,
        ];
    }

    private function attachRealPositionToRequest(Request $request, UserPosition $userPosition): void
    {
        $request->attributes->set(self::REAL_ACTIVE_USER_POSITION_ATTRIBUTE, $userPosition);
        $request->attributes->set('realActiveUserPosition', $userPosition);
        $request->attributes->set(self::ACTIVE_YEAR_ATTRIBUTE, $this->activeYear($request));
    }

    private function actingPosition(Request $request, UserPosition $realActiveUserPosition): ?UserPosition
    {
        $actingContextData = $this->adminSuperActingContextData($request);

        if ($actingContextData === null) {
            return null;
        }

        $jabatan = Jabatan::query()
            ->active()
            ->whereKey($actingContextData['jabatan_id'])
            ->first();

        $instansi = Instansi::query()
            ->active()
            ->whereKey($actingContextData['instansi_id'])
            ->first();

        $unitKerja = UnitKerja::query()
            ->active()
            ->effective()
            ->whereKey($actingContextData['unit_kerja_id'])
            ->first();

        $pptkUserPosition = $this->specialUserPosition($actingContextData['pptk_user_position_id']);
        $budUserPosition = $this->specialUserPosition($actingContextData['bud_user_position_id']);

        if (! $jabatan instanceof Jabatan || ! $instansi instanceof Instansi || ! $unitKerja instanceof UnitKerja) {
            $this->forgetAdminSuperActingContext($request);

            return null;
        }

        if ($this->adminSuperPositionScope->validateCombination($jabatan, $instansi, $unitKerja, $pptkUserPosition, $budUserPosition) !== []) {
            $this->forgetAdminSuperActingContext($request);

            return null;
        }

        $actingUserPosition = clone $realActiveUserPosition;
        $actingUserPosition->setAttribute('jabatan_id', $jabatan->getKey());
        $actingUserPosition->setAttribute('instansi_id', $instansi->getKey());
        $actingUserPosition->setAttribute('unit_kerja_id', $unitKerja->getKey());
        $actingUserPosition->setAttribute('is_acting_context', true);
        $actingUserPosition->setAttribute('real_user_position_id', $realActiveUserPosition->getKey());
        $actingUserPosition->setAttribute('acting_context', $actingContextData);
        $actingUserPosition->setAttribute('acting_pptk_user_position_id', $pptkUserPosition?->getKey());
        $actingUserPosition->setAttribute('acting_bud_user_position_id', $budUserPosition?->getKey());
        $actingUserPosition->setRelation('jabatan', $jabatan);
        $actingUserPosition->setRelation('instansi', $instansi);
        $actingUserPosition->setRelation('unitKerja', $unitKerja);

        if ($pptkUserPosition instanceof UserPosition) {
            $actingUserPosition->setRelation('actingPptkUserPosition', $pptkUserPosition);
        }

        if ($budUserPosition instanceof UserPosition) {
            $actingUserPosition->setRelation('actingBudUserPosition', $budUserPosition);
        }

        return $actingUserPosition;
    }

    private function specialUserPosition(?int $userPositionId): ?UserPosition
    {
        if ($userPositionId === null) {
            return null;
        }

        $userPosition = UserPosition::query()
            ->with(['user', 'jabatan', 'instansi', 'unitKerja'])
            ->availableForSelection()
            ->withActiveReferences()
            ->whereKey($userPositionId)
            ->first();

        return $userPosition instanceof UserPosition ? $userPosition : null;
    }

    private function sessionInteger(Request $request, string $key): ?int
    {
        $value = $request->session()->get($key);

        if (! filled($value)) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private function sessionString(Request $request, string $key): ?string
    {
        $value = $request->session()->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
