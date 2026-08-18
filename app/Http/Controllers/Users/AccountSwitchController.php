<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\UserPosition;
use App\Services\User\PositionSwitcher;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountSwitchController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function positions()
    {
        $positions = $this->availablePositions();
        $activePosition = $positions->firstWhere('is_active', true);

        return view('users.positions-switch', [
            'positions' => $positions,
            'activePosition' => $activePosition,
            'adminSuperContext' => $this->adminSuperContext($activePosition),
        ]);
    }

    public function switch(Request $request, PositionSwitcher $switcher)
    {
        $request->validate([
            'position_id' => ['required', 'string'], // terenkripsi
        ]);

        try {
            Log::channel('module_users')->info('Account switch request', [
                'actor_id' => auth()->id(),
            ]);

            $decodedId = EncryptedId::decode($request->string('position_id'));
        } catch (Throwable $e) {
            Log::channel('module_users')->warning('Account switch failed: invalid encrypted position', [
                'actor_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Posisi tidak valid.'], 422);
            }

            return back()->withErrors(['position_id' => 'Posisi tidak valid.']);
        }

        try {
            $position = UserPosition::whereKey($decodedId)->firstOrFail();

            if ($position->user_id !== $request->user()->id) {
                Log::channel('module_users')->warning('Account switch blocked: forbidden position owner', [
                    'actor_id' => auth()->id(),
                    'position_id' => $position->id,
                ]);
                abort(403);
            }

            $position = DB::transaction(function () use ($switcher, $request, $position) {
                return $switcher->setActive($request->user(), $position->id);
            });

            Log::channel('module_users')->info('Account switched', [
                'actor_id' => auth()->id(),
                'position_id' => $position->id,
                'jabatan_id' => $position->jabatan_id,
            ]);

            session([
                'active_position_id' => $position->id,
                'active_role' => $position->jabatan->nama,
                'active_instansi' => ($position->instansi) ? $position->instansi->nama : null,
                'active_unit_kerja' => ($position->unitKerja) ? $position->unitKerja->nama : null,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Akun jabatan aktif diganti ke: '.$position->jabatan->nama,
                    'redirectTo' => ($position->jabatan_id == 1) ? route('login.post') : null,
                ]);
            }

            if ($position->jabatan_id == 1) {
                return redirect()->route('login.post');
            }

            return back()->with('status', 'Akun jabatan aktif diganti ke: '.$position->jabatan->nama);
        } catch (Throwable $e) {
            Log::channel('module_users')->error('Account switch failed', [
                'actor_id' => auth()->id(),
                'position_id' => $decodedId ?? null,
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Gagal mengganti akun aktif.'], 500);
            }

            return back()->withErrors(['position_id' => 'Gagal mengganti akun aktif.']);
        }
    }

    private function availablePositions()
    {
        return auth()->user()
            ->positions()
            ->with(['jabatan', 'instansi', 'unitKerja'])
            ->orderByDesc('is_active')
            ->orderBy('jabatan_id')
            ->orderBy('instansi_id')
            ->orderBy('unit_kerja_id')
            ->get();
    }

    private function adminSuperContext(?UserPosition $activePosition): ?array
    {
        if ((int) ($activePosition?->jabatan_id ?? 0) !== 1 || ! session()->has('acting_unit_kerja_id')) {
            return null;
        }

        $jabatanId = (int) session('acting_jabatan_id');

        if ($jabatanId <= 1) {
            return null;
        }

        $specialUserLabel = null;
        $specialUserPositionId = null;

        if ($budUserPositionId = session('acting_bud_user_id')) {
            $specialUserLabel = 'User BUD / Kuasa BUD';
            $specialUserPositionId = $budUserPositionId;
        } elseif ($pptkUserPositionId = session('acting_pptk_user_id')) {
            $specialUserLabel = 'User PPTK';
            $specialUserPositionId = $pptkUserPositionId;
        }

        $specialUserPosition = $specialUserPositionId
            ? UserPosition::with(['user', 'jabatan', 'instansi', 'unitKerja'])->find($specialUserPositionId)
            : null;

        return [
            'login_user' => auth()->user()?->nama ?? '-',
            'login_role' => $activePosition->jabatan?->nama ?? 'Admin Super',
            'jabatan' => Jabatan::find($jabatanId)?->nama ?? session('acting_jabatan_name', '-'),
            'instansi' => ($instansiId = session('acting_instansi_id')) ? (Instansi::find($instansiId)?->nama ?? '-') : '-',
            'unit_kerja' => ($unitKerjaId = session('acting_unit_kerja_id')) ? (UnitKerja::find($unitKerjaId)?->nama ?? '-') : '-',
            'tahun_aktif' => session('tahun_aktif') ?? auth()->user()?->tahun_aktif ?? now()->year,
            'special_user_label' => $specialUserLabel,
            'special_user_name' => $specialUserPosition?->user?->nama,
            'special_user_nip' => $specialUserPosition?->user?->nip,
            'special_user_nik' => $specialUserPosition?->user?->nik,
            'special_user_jabatan' => $specialUserPosition?->jabatan?->nama,
            'special_user_instansi' => $specialUserPosition?->instansi?->nama,
            'special_user_unit_kerja' => $specialUserPosition?->unitKerja?->nama,
        ];
    }
}
