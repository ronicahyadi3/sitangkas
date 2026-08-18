<?php

namespace App\Http\Controllers\Users;

use App\Actions\UserManagement\CreateManagedUser;
use App\Actions\UserManagement\DeleteManagedUser;
use App\Actions\UserManagement\UpdateManagedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Models\Jabatan;
use App\Models\User;
use App\Models\UserPositionDocument;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        protected ActivePositionService $activePositionService,
        protected UserManagementAccessService $userManagementAccessService
    ) {}

    // Halaman index (hanya render Blade & master data untuk select)
    public function index(Request $request)
    {
        $actor = $this->activePositionService->get();
        $allowedJabatanIds = $this->userManagementAccessService->allowedJabatanIds($actor);

        $jabatans = Jabatan::query()
            ->when(! empty($allowedJabatanIds), fn ($query) => $query->whereIn('id', $allowedJabatanIds))
            ->orderBy('nama')
            ->get();
        $userManagementContext = $this->userManagementAccessService->context($actor);
        $userAccountTypes = $this->userAccountTypes();
        $userAccountStatuses = $this->userAccountStatuses();
        $positionDocumentTypes = UserPositionDocument::typeOptions();

        return view('users.index', compact(
            'jabatans',
            'userManagementContext',
            'userAccountTypes',
            'userAccountStatuses',
            'positionDocumentTypes'
        ));
    }

    // Endpoint DataTables server-side
    public function datatable(Request $request)
    {
        $actor = $this->activePositionService->get();
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) data_get($request->input('search'), 'value', ''));

        // Map index kolom -> field DB
        $columns = [
            0 => null,               // #
            1 => 'nik',
            2 => 'nama',
            3 => 'email',
            4 => 'positions_count',
            5 => null,               // actions
        ];

        $orderColIdx = (int) data_get($request->input('order'), '0.column', 2);
        $orderDir = data_get($request->input('order'), '0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $orderCol = $columns[$orderColIdx] ?: 'nama';

        $visibleBase = User::query()->withCount('positions');
        $visibleBase = $this->userManagementAccessService->applyVisibleUsersScope($visibleBase, $actor);

        $base = clone $visibleBase;

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $recordsTotal = (clone $visibleBase)->count();
        $recordsFiltered = (clone $base)->count();

        $users = $base
            ->orderBy($orderCol, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        // Susun baris untuk DataTables
        $data = [];
        $index = $start + 1;
        foreach ($users as $u) {
            $canEditProfile = $this->userManagementAccessService->canEditUserProfile($u, $actor);
            $canDeleteProfile = $this->userManagementAccessService->canManageUser($u, $actor);
            $canManagePositions = $this->userManagementAccessService->canViewUser($u, $actor);
            $editUrl = route('users.update', $u);
            $destroyUrl = route('users.destroy', $u);
            $positionsUrl = route('users.positions.index', $u);
            $addPosUrl = route('users.positions.store', $u);
            $securityUrl = route('users.security.show', $u);

            $payload = [
                'id_enc' => $u->getRouteKey(),
                'nik' => $u->nik,
                'nip' => $u->nip,
                'nama' => $u->nama,
                'email' => $u->email,
                'account_type' => $u->account_type,
                'status' => $u->status,
                'status_reason' => $u->status_reason,
                'tahun_aktif' => $u->tahun_aktif,
                'update_url' => $editUrl,
                'destroy_url' => $destroyUrl,
                'pos_index_url' => $positionsUrl,
                'addpos_url' => $addPosUrl,
                'security_url' => $securityUrl,
                'can_edit_profile' => $canEditProfile,
                'can_delete_profile' => $canDeleteProfile,
                'can_manage_positions' => $canManagePositions,
                'can_manage_security' => $canDeleteProfile,
            ];
            $payloadJson = e(json_encode($payload)); // escape for HTML data attr

            $nikDisplay = '
                <div class="users-table-cell">
                    <div class="users-table-cell__title font-monospace">'.e($u->nik).'</div>
                    <div class="users-table-cell__meta">'.($u->nip ? 'NIP '.e($u->nip) : 'NIP belum diisi').'</div>
                </div>
            ';

            $namaDisplay = '
                <div class="users-table-cell">
                    <div class="users-table-cell__title">'.e($u->nama).'</div>
                    <div class="users-table-cell__meta">'.($u->positions_count > 0 ? 'Sudah memiliki '.$u->positions_count.' posisi' : 'Belum memiliki posisi').'</div>
                </div>
            ';

            $emailDisplay = $u->email
                ? '
                    <div class="users-table-cell">
                        <div class="users-table-cell__title">'.e($u->email).'</div>
                        <div class="users-table-cell__meta">Email terdaftar</div>
                    </div>
                '
                : '<span class="users-muted-pill">Belum diisi</span>';

            $editButton = $canEditProfile
                ? '<button class="btn btn-sm btn-outline-primary btnEditUser users-action-btn" data-user=\''.$payloadJson.'\' title="Edit user">
                    <i class="fa fa-pen"></i><span class="d-none d-xl-inline ms-1">Edit</span>
                  </button>'
                : '<button class="btn btn-sm btn-outline-primary users-action-btn" type="button" disabled title="Profil global user ini hanya bisa diedit oleh Admin Super karena memiliki jabatan yang tidak boleh Anda kelola.">
                    <i class="fa fa-pen"></i><span class="d-none d-xl-inline ms-1">Edit</span>
                  </button>';

            $positionsButton = $canManagePositions
                ? '<button class="btn btn-sm btn-outline-secondary btnManagePos users-action-btn" data-user=\''.$payloadJson.'\' title="Kelola posisi">
                    <i class="fa fa-briefcase"></i><span class="d-none d-xl-inline ms-1">Posisi</span>
                  </button>'
                : '';

            $securityButton = '<button class="btn btn-sm btn-outline-dark btnManageSecurity users-action-btn" data-user=\''.$payloadJson.'\' title="Keamanan akun">
                    <i class="fa fa-shield-halved"></i><span class="d-none d-xl-inline ms-1">Keamanan</span>
                  </button>';

            $deleteButton = $canDeleteProfile
                ? '<button class="btn btn-sm btn-outline-danger btnDeleteUser users-action-btn" data-user=\''.$payloadJson.'\' title="Hapus user">
                    <i class="fa fa-trash"></i><span class="d-none d-xl-inline ms-1">Hapus</span>
                  </button>'
                : '<button class="btn btn-sm btn-outline-danger users-action-btn" type="button" disabled title="User ini hanya dapat dihapus bila seluruh posisinya masuk scope Anda.">
                    <i class="fa fa-trash"></i><span class="d-none d-xl-inline ms-1">Hapus</span>
                  </button>';

            $actions = '
              <div class="d-flex justify-content-center flex-wrap gap-1">
                '.$editButton.'
                '.$positionsButton.'
                '.$securityButton.'
                '.$deleteButton.'
              </div>
            ';

            $data[] = [
                'index' => $index++,
                'nik' => $nikDisplay,
                'nama' => $namaDisplay,
                'email' => $emailDisplay,
                'positions_count' => '<span class="users-count-pill"><i class="fa fa-briefcase me-1"></i>'.$u->positions_count.' posisi</span>',
                'positions_total_raw' => $u->positions_count,
                'actions' => $actions,
            ];
        }

        Log::channel('module_users')->debug('Users datatable request', [
            'user_id' => auth()->id(),
            'draw' => $draw,
            'start' => $start,
            'length' => $length,
            'search' => $search,
            'records_total' => $recordsTotal,
            'records_filtered' => $recordsFiltered,
        ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(UserStoreRequest $request, CreateManagedUser $createManagedUser)
    {
        $actor = $this->activePositionService->get();

        try {
            $user = $createManagedUser->handle($request, $actor);

            if ($request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'User dibuat.',
                    'user' => $this->buildUserPayload($user),
                    'prompt_position_setup' => true,
                ]);
            }

            return back()->with('status', 'User dibuat.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Gagal membuat user.'], 500);
            }

            return back()->withErrors(['error' => 'Gagal membuat user.'])->withInput();
        }
    }

    public function update(UserUpdateRequest $request, User $user, UpdateManagedUser $updateManagedUser)
    {
        $actor = $this->activePositionService->get();

        if (! $this->userManagementAccessService->canEditUserProfile($user, $actor)) {
            return $this->managementDeniedResponse($request, 'User ini tidak termasuk scope pengelolaan jabatan aktif Anda.');
        }

        try {
            $updateManagedUser->handle($request, $user, $actor);

            if ($request->ajax()) {
                return response()->json(['ok' => true, 'message' => 'User diperbarui.']);
            }

            return back()->with('status', 'User diperbarui.');
        } catch (Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Gagal memperbarui user.'], 500);
            }

            return back()->withErrors(['error' => 'Gagal memperbarui user.'])->withInput();
        }
    }

    public function destroy(Request $request, User $user, DeleteManagedUser $deleteManagedUser)
    {
        $actor = $this->activePositionService->get();

        if (! $this->userManagementAccessService->canManageUser($user, $actor)) {
            return $this->managementDeniedResponse($request, 'User ini tidak termasuk scope pengelolaan jabatan aktif Anda.');
        }

        try {
            $deleteManagedUser->handle($request, $user, $actor);

            if ($request->ajax()) {
                return response()->json(['ok' => true, 'message' => 'User dihapus.']);
            }

            return back()->with('status', 'User dihapus.');
        } catch (Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Gagal menghapus user.'], 500);
            }

            return back()->withErrors(['error' => 'Gagal menghapus user.']);
        }
    }

    public function pptk(Request $request, ActivePositionService $activePositionService)
    {
        $users = $activePositionService->get();

        $unitKerjaId = $request->filled('unit_kerja_id')
            ? EncryptedId::decode($request->unit_kerja_id)
            : $users->unitKerja->id;

        Log::channel('module_users')->debug('Users PPTK lookup request', [
            'actor_id' => auth()->id(),
            'unit_kerja_id' => $unitKerjaId,
            'search' => $request->search,
        ]);

        if (! $unitKerjaId) {
            return response()->json([], 200);
        }

        $search = $request->search;

        $pptkUsers = User::whereHas('positions', function ($q) use ($unitKerjaId) {
            $q->where('unit_kerja_id', $unitKerjaId)
                ->where('jabatan_id', 8);
        })
            ->with(['positions' => function ($q) use ($unitKerjaId) {
                $q->where('unit_kerja_id', $unitKerjaId)
                    ->where('jabatan_id', 8);
            }])
            ->when($search, function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%");
            })
            ->orderBy('nama')
            ->get()
            ->map(function ($user) {
                $position = $user->positions->first();

                return [
                    'id' => EncryptedId::encode($position->id),
                    'user_id' => EncryptedId::encode($user->id),
                    'nama' => $user->nama,
                ];
            });

        return response()->json($pptkUsers, 200);
    }

    public function bud(Request $request)
    {
        $open = ($request->open === 'true' || $request->open === true);
        $unitKerjaId = null;
        $jabatan_id = null;

        if ($request->filled('unit_kerja_id')) {
            try {
                $unitKerjaId = EncryptedId::decode($request->unit_kerja_id);
            } catch (Throwable) {
                $unitKerjaId = null;
            }
        }

        if ($request->filled('jabatan_id')) {
            try {
                $jabatan_id = EncryptedId::decode($request->jabatan_id);
            } catch (Throwable) {
                $jabatan_id = null;
            }
        }

        Log::channel('module_users')->debug('Users BUD lookup request', [
            'actor_id' => auth()->id(),
            'open' => (bool) $open,
            'unit_kerja_id' => $unitKerjaId,
            'jabatan_id' => $jabatan_id,
        ]);

        $budUsers = User::query()
            ->select('id', 'nama')
            ->whereHas('positions', function ($q) use ($jabatan_id, $unitKerjaId) {
                $q->whereIn('jabatan_id', [2, 3]);
                if ($jabatan_id) {
                    $q->where('jabatan_id', $jabatan_id);
                }
                if ($unitKerjaId) {
                    $q->where('unit_kerja_id', $unitKerjaId);
                }
            })
            ->with(['positions' => function ($q) use ($jabatan_id, $unitKerjaId) {
                $q->whereIn('jabatan_id', [2, 3]);
                if ($jabatan_id) {
                    $q->where('jabatan_id', $jabatan_id);
                }
                if ($unitKerjaId) {
                    $q->where('unit_kerja_id', $unitKerjaId);
                }
            }])
            ->orderBy('nama')
            ->get()
            ->map(function ($user) use ($open) {
                $position = $user->positions->first();
                $data = [
                    'id' => EncryptedId::encode($position->id),
                    'user_id' => EncryptedId::encode($user->id),
                    'nama' => $user->nama,
                ];

                if ($open) {
                    $data['position_id'] = $position->id;
                }

                return $data;
            });

        return response()->json($budUsers);
    }

    private function buildUserPayload(User $user): array
    {
        return [
            'id_enc' => $user->getRouteKey(),
            'nik' => $user->nik,
            'nip' => $user->nip,
            'nama' => $user->nama,
            'email' => $user->email,
            'account_type' => $user->account_type,
            'status' => $user->status,
            'status_reason' => $user->status_reason,
            'tahun_aktif' => $user->tahun_aktif,
            'update_url' => route('users.update', $user),
            'destroy_url' => route('users.destroy', $user),
            'pos_index_url' => route('users.positions.index', $user),
            'addpos_url' => route('users.positions.store', $user),
            'security_url' => route('users.security.show', $user),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function userAccountTypes(): array
    {
        return [
            User::ACCOUNT_TYPE_PERSONAL => 'Personal',
            User::ACCOUNT_TYPE_FUNCTIONAL => 'Fungsional',
            User::ACCOUNT_TYPE_SERVICE => 'Service',
            User::ACCOUNT_TYPE_EMERGENCY => 'Darurat',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function userAccountStatuses(): array
    {
        return [
            User::STATUS_ACTIVE => 'Aktif',
            User::STATUS_PENDING => 'Pending',
            User::STATUS_INACTIVE => 'Nonaktif',
            User::STATUS_LOCKED => 'Terkunci',
            User::STATUS_SUSPENDED => 'Suspended',
        ];
    }

    private function managementDeniedResponse(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()
            ->route('users.index')
            ->withErrors(['access' => $message]);
    }
}
