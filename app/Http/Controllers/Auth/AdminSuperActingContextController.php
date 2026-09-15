<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EnforceSingleDeviceAuthentication;
use App\Actions\Auth\RecordAuthenticationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreAdminSuperActingContextRequest;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\LoginEvent;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\AdminSuperPositionScope;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSuperActingContextController extends Controller
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private AdminSuperPositionScope $positionScope
    ) {}

    public function create(Request $request): View|RedirectResponse|JsonResponse
    {
        $activeUserPosition = $this->activeAdminSuperPositionOrResponse($request);

        if (! $activeUserPosition instanceof UserPosition) {
            return $activeUserPosition;
        }

        $user = $this->currentUserContext->user($request);

        return view('auth.postLogin', [
            'user' => $user,
            'activeYear' => $this->currentUserContext->activeYear($request),
            'realActiveUserPosition' => $activeUserPosition,
            'jabatans' => $this->jabatanOptions(),
        ]);
    }

    public function store(
        StoreAdminSuperActingContextRequest $request,
        RecordAuthenticationEvent $recordAuthenticationEvent,
        EnforceSingleDeviceAuthentication $singleDeviceAuthentication
    ): RedirectResponse {
        $user = $this->currentUserContext->user($request);
        $realActiveUserPosition = $request->realActiveUserPosition();
        $actingContextData = $request->actingContextData();

        $request->session()->forget(array_values($this->currentUserContext->adminSuperActingContextSessionKeys()));
        $request->session()->put($request->actingSessionData());

        if ($user instanceof User) {
            $singleDeviceAuthentication->disableRememberMeForCurrentSessionOnly($request);
        }

        $recordAuthenticationEvent->handle($request, $user instanceof User ? $user : null, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_CONTEXT_SWITCHED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Konteks acting Admin Super berhasil dipilih.',
            'auth_method' => 'session',
            'http_status' => 302,
            'remember_me' => null,
            'session_id_hash' => $recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'context_type' => 'admin_super_acting_context',
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'acting_context' => $actingContextData,
                'tahun_aktif' => $this->currentUserContext->activeYear($request),
                'remember_me_disabled' => true,
            ],
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Konteks Admin Super berhasil dipilih.');
    }

    public function instansiOptions(Request $request): JsonResponse
    {
        if (! $this->activeAdminSuperPosition($request) instanceof UserPosition) {
            return $this->jsonAccessDenied();
        }

        $jabatan = $this->activeJabatan((int) $request->integer('jabatan_id'));

        if (! $jabatan instanceof Jabatan) {
            return response()->json([
                'ok' => false,
                'message' => 'Jabatan tidak ditemukan.',
            ], 404);
        }

        $options = $this->positionScope
            ->instansiOptionsForJabatan($jabatan)
            ->map(fn (Instansi $instansi): array => [
                'id' => $instansi->getKey(),
                'text' => $instansi->nama,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'allow_null' => false,
            'options' => $options,
        ]);
    }

    public function unitKerjaOptions(Request $request): JsonResponse
    {
        if (! $this->activeAdminSuperPosition($request) instanceof UserPosition) {
            return $this->jsonAccessDenied();
        }

        $jabatan = $this->activeJabatan((int) $request->integer('jabatan_id'));
        $instansi = $this->activeInstansi((int) $request->integer('instansi_id'));

        if (! $jabatan instanceof Jabatan) {
            return response()->json([
                'ok' => false,
                'message' => 'Jabatan tidak ditemukan.',
            ], 404);
        }

        if (! $instansi instanceof Instansi) {
            return response()->json([
                'ok' => false,
                'message' => 'Instansi tidak ditemukan.',
            ], 404);
        }

        $options = $this->positionScope
            ->unitKerjaOptionsForJabatanAndInstansi($jabatan, $instansi)
            ->map(fn ($unitKerja): array => [
                'id' => $unitKerja->getKey(),
                'text' => $unitKerja->nama,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'options' => $options,
        ]);
    }

    public function specialUserPositionOptions(Request $request): JsonResponse
    {
        if (! $this->activeAdminSuperPosition($request) instanceof UserPosition) {
            return $this->jsonAccessDenied();
        }

        $jabatan = $this->activeJabatan((int) $request->integer('jabatan_id'));
        $unitKerja = $this->activeUnitKerja((int) $request->integer('unit_kerja_id'));

        if (! $jabatan instanceof Jabatan) {
            return response()->json([
                'ok' => false,
                'message' => 'Jabatan tidak ditemukan.',
            ], 404);
        }

        if (! $unitKerja instanceof UnitKerja) {
            return response()->json([
                'ok' => false,
                'message' => 'Unit kerja tidak ditemukan.',
            ], 404);
        }

        $options = $this->positionScope
            ->specialUserPositionOptionsForJabatanAndUnitKerja($jabatan, $unitKerja)
            ->map(function (UserPosition $userPosition): array {
                $label = $this->specialUserPositionLabel($userPosition);

                return [
                    'id' => $userPosition->getKey(),
                    'nama' => $label,
                    'text' => $label,
                ];
            })
            ->values();

        return response()->json($options);
    }

    private function activeAdminSuperPositionOrResponse(Request $request): UserPosition|RedirectResponse|JsonResponse
    {
        $activeUserPosition = $this->activeAdminSuperPosition($request);

        if ($activeUserPosition instanceof UserPosition) {
            return $activeUserPosition;
        }

        if ($request->expectsJson()) {
            return $this->jsonAccessDenied();
        }

        if (! filled($this->currentUserContext->activeUserPositionId($request))) {
            return redirect()
                ->route('positions.index')
                ->with('status', 'Silakan pilih posisi nyata Admin Super terlebih dahulu.');
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Halaman pemilihan acting context hanya tersedia untuk Admin Super.');
    }

    private function activeAdminSuperPosition(Request $request): ?UserPosition
    {
        $activeUserPosition = $this->currentUserContext->realActivePosition($request);

        if (! $activeUserPosition instanceof UserPosition) {
            return null;
        }

        $activeUserPosition->loadMissing('jabatan');

        if (! $activeUserPosition->jabatan instanceof Jabatan) {
            return null;
        }

        return $this->positionScope->isAdminSuperJabatan($activeUserPosition->jabatan)
            ? $activeUserPosition
            : null;
    }

    private function activeJabatan(int $id): ?Jabatan
    {
        return Jabatan::query()
            ->active()
            ->whereKey($id)
            ->first();
    }

    private function activeInstansi(int $id): ?Instansi
    {
        return Instansi::query()
            ->active()
            ->whereKey($id)
            ->first();
    }

    private function activeUnitKerja(int $id): ?UnitKerja
    {
        return UnitKerja::query()
            ->active()
            ->effective()
            ->whereKey($id)
            ->first();
    }

    /**
     * @return array<int, array{id: int|string|null, nama: string, special_user: string|null}>
     */
    private function jabatanOptions(): array
    {
        return $this->positionScope
            ->jabatanOptions()
            ->map(fn (Jabatan $jabatan): array => [
                'id' => $jabatan->getKey(),
                'nama' => $jabatan->nama,
                'special_user' => $this->positionScope->specialUserRequirementForJabatan($jabatan),
            ])
            ->values()
            ->all();
    }

    private function specialUserPositionLabel(UserPosition $userPosition): string
    {
        $userPosition->loadMissing(['user', 'jabatan', 'unitKerja']);

        return trim(implode(' - ', array_filter([
            $userPosition->user?->nama,
            $userPosition->user?->nik,
            $userPosition->jabatan?->nama,
            $userPosition->unitKerja?->nama,
        ])));
    }

    private function jsonAccessDenied(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Halaman pemilihan acting context hanya tersedia untuk Admin Super.',
        ], 403);
    }
}
