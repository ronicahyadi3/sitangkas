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
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Models\UserPositionDocument;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use App\Services\User\UserManagementAuditLogger;
use App\Support\EncryptedId;
use App\Support\UserManagement\UserDatatablePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function __construct(
        protected ActivePositionService $activePositionService,
        protected UserManagementAccessService $userManagementAccessService,
        protected UserManagementAuditLogger $userManagementAuditLogger,
        protected UserDatatablePresenter $userDatatablePresenter
    ) {}

    // Halaman index (hanya render Blade & master data untuk select)
    public function index(Request $request)
    {
        $actor = $this->activePositionService->managementActor();
        $allowedJabatanIds = $this->userManagementAccessService->allowedJabatanIds($actor);

        $jabatans = Jabatan::query()
            ->when(! empty($allowedJabatanIds), fn ($query) => $query->whereIn('id', $allowedJabatanIds))
            ->orderBy('nama')
            ->get();
        $userManagementContext = $this->userManagementAccessService->context($actor);
        $userAccountTypes = $this->userAccountTypes();
        $userAccountStatuses = $this->userAccountStatuses();
        $editableUserAccountStatuses = $this->editableUserAccountStatuses();
        $positionDocumentTypes = UserPositionDocument::typeOptions();

        return view('users.index', compact(
            'jabatans',
            'userManagementContext',
            'userAccountTypes',
            'userAccountStatuses',
            'editableUserAccountStatuses',
            'positionDocumentTypes'
        ));
    }

    // Endpoint DataTables server-side
    public function datatable(Request $request)
    {
        $actor = $this->activePositionService->managementActor();
        $start = (int) $request->input('start', 0);
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $filters = $this->datatableFilters($request);
        $index = $start + 1;

        $query = User::query()
            ->select([
                'id',
                'nik',
                'nip',
                'nama',
                'email',
                'account_type',
                'status',
                'status_reason',
                'tahun_aktif',
                'locked_until',
                'must_change_password',
                'password_expires_at',
                'mfa_secret',
                'mfa_enabled_at',
                'mfa_confirmed_at',
                'mfa_pending_secret',
                'mfa_pending_secret_created_at',
                'created_by_user_id',
            ])
            ->with([
                'lastUsedUserPosition' => function (Relation $positions) use ($actor): void {
                    $this->eagerLoadDatatableDisplayPosition($positions, $actor);
                },
                'latestActiveUserPosition' => function (Relation $positions) use ($actor): void {
                    $this->eagerLoadDatatableDisplayPosition($positions, $actor);
                },
            ])
            ->withCount([
                'positions' => function (Builder $positions) use ($actor): void {
                    $this->userManagementAccessService->applyVisiblePositionsScope($positions, $actor);
                },
                'activeUserPositions as active_positions_count' => function (Builder $positions) use ($actor): void {
                    $this->userManagementAccessService->applyVisiblePositionsScope($positions, $actor);
                },
            ]);
        $query = $this->userManagementAccessService->applyVisibleUsersScope($query, $actor);
        $query = $this->applyDatatableFilters($query, $filters, $actor);

        Log::channel('module_users')->debug('Users datatable request', [
            'user_id' => auth()->id(),
            'draw' => (int) $request->input('draw', 1),
            'start' => $start,
            'length' => (int) $request->input('length', 10),
            'search' => $search,
            'filters' => $filters,
        ]);

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($search): void {
                if ($search === '') {
                    return;
                }

                $query->where(function ($query) use ($search): void {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->addColumn('index', function () use (&$index): int {
                return $index++;
            })
            ->editColumn('nik', fn (User $user): string => $this->userDatatablePresenter->nikColumn($user))
            ->editColumn('nama', fn (User $user): string => $this->userDatatablePresenter->nameColumn($user))
            ->editColumn('email', fn (User $user): string => $this->userDatatablePresenter->emailColumn($user))
            ->addColumn('account_status', fn (User $user): string => $this->userDatatablePresenter->accountStatusColumn($user))
            ->addColumn('security_status', fn (User $user): string => $this->userDatatablePresenter->securityStatusColumn($user))
            ->addColumn('used_position', fn (User $user): string => $this->userDatatablePresenter->usedPositionColumn($user))
            ->editColumn('positions_count', fn (User $user): string => $this->userDatatablePresenter->positionsCountColumn($user))
            ->addColumn('positions_total_raw', fn (User $user): int => (int) $user->positions_count)
            ->addColumn('actions', fn (User $user): string => $this->userDatatablePresenter->actionsColumn($user, $actor))
            ->rawColumns(['nik', 'nama', 'email', 'account_status', 'security_status', 'used_position', 'positions_count', 'actions'])
            ->toJson();
    }

    public function store(UserStoreRequest $request, CreateManagedUser $createManagedUser)
    {
        $actor = $this->activePositionService->managementActor();

        try {
            $user = $createManagedUser->handle($request, $actor);

            if ($request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'User dibuat.',
                    'user' => $this->userDatatablePresenter->payload($user),
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
        $actor = $this->activePositionService->managementActor();

        if (! $this->userManagementAccessService->canEditUserProfile($user, $actor)) {
            return $this->managementDeniedResponse(
                $request,
                'User ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                UserManagementAuditEvent::EVENT_USER_UPDATED,
                $user
            );
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
        $actor = $this->activePositionService->managementActor();

        if (! $this->userManagementAccessService->canManageUser($user, $actor)) {
            return $this->managementDeniedResponse(
                $request,
                'User ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                UserManagementAuditEvent::EVENT_USER_DELETED,
                $user
            );
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
            ->map(function ($user) {
                $position = $user->positions->first();
                $data = [
                    'id' => EncryptedId::encode($position->id),
                    'user_id' => EncryptedId::encode($user->id),
                    'nama' => $user->nama,
                ];

                return $data;
            });

        return response()->json($budUsers);
    }

    /**
     * @return array{
     *     status: ?string,
     *     account_type: ?string,
     *     jabatan_id: ?int,
     *     instansi_id: ?int,
     *     unit_kerja_id: ?int,
     *     position_state: ?string
     * }
     */
    private function datatableFilters(Request $request): array
    {
        return [
            'status' => $this->allowedStringFilter(
                $request,
                'status',
                array_keys($this->userAccountStatuses())
            ),
            'account_type' => $this->allowedStringFilter(
                $request,
                'account_type',
                array_keys($this->userAccountTypes())
            ),
            'jabatan_id' => $this->decodedIdFilter($request, 'jabatan_id'),
            'instansi_id' => $this->decodedIdFilter($request, 'instansi_id'),
            'unit_kerja_id' => $this->decodedIdFilter($request, 'unit_kerja_id'),
            'position_state' => $this->allowedStringFilter($request, 'position_state', [
                'has_positions',
                'no_positions',
                'has_active_position',
                'no_active_position',
            ]),
        ];
    }

    /**
     * @param  array{
     *     status: ?string,
     *     account_type: ?string,
     *     jabatan_id: ?int,
     *     instansi_id: ?int,
     *     unit_kerja_id: ?int,
     *     position_state: ?string
     * }  $filters
     */
    private function applyDatatableFilters(Builder $query, array $filters, ?UserPosition $actor): Builder
    {
        return $query
            ->when($filters['status'] !== null, fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when($filters['account_type'] !== null, fn (Builder $query): Builder => $query->where('account_type', $filters['account_type']))
            ->when(
                $this->hasPositionAttributeFilter($filters),
                fn (Builder $query): Builder => $query->whereHas('userPositions', function (Builder $positions) use ($filters, $actor): void {
                    $this->userManagementAccessService->applyVisiblePositionsScope($positions, $actor);
                    $this->applyPositionAttributeFilters($positions, $filters);
                })
            )
            ->when($filters['position_state'] === 'has_positions', function (Builder $query) use ($actor): Builder {
                return $query->whereHas('userPositions', function (Builder $positions) use ($actor): void {
                    $this->userManagementAccessService->applyVisiblePositionsScope($positions, $actor);
                });
            })
            ->when($filters['position_state'] === 'no_positions', function (Builder $query) use ($actor): Builder {
                return $query->whereDoesntHave('userPositions', function (Builder $positions) use ($actor): void {
                    $this->userManagementAccessService->applyVisiblePositionsScope($positions, $actor);
                });
            })
            ->when($filters['position_state'] === 'has_active_position', function (Builder $query) use ($actor): Builder {
                return $query->whereHas('userPositions', function (Builder $positions) use ($actor): void {
                    $this->userManagementAccessService->applyVisiblePositionsScope($positions, $actor);
                    $positions->where('is_active', true);
                });
            })
            ->when($filters['position_state'] === 'no_active_position', function (Builder $query) use ($actor): Builder {
                return $query->whereDoesntHave('userPositions', function (Builder $positions) use ($actor): void {
                    $this->userManagementAccessService->applyVisiblePositionsScope($positions, $actor);
                    $positions->where('is_active', true);
                });
            });
    }

    /**
     * @param  array{jabatan_id: ?int, instansi_id: ?int, unit_kerja_id: ?int}  $filters
     */
    private function hasPositionAttributeFilter(array $filters): bool
    {
        return $filters['jabatan_id'] !== null
            || $filters['instansi_id'] !== null
            || $filters['unit_kerja_id'] !== null;
    }

    /**
     * @param  array{jabatan_id: ?int, instansi_id: ?int, unit_kerja_id: ?int}  $filters
     */
    private function applyPositionAttributeFilters(Builder $positions, array $filters): void
    {
        if ($filters['jabatan_id'] !== null) {
            $positions->where('jabatan_id', $filters['jabatan_id']);
        }

        if ($filters['instansi_id'] !== null) {
            $positions->where('instansi_id', $filters['instansi_id']);
        }

        if ($filters['unit_kerja_id'] !== null) {
            $positions->where('unit_kerja_id', $filters['unit_kerja_id']);
        }
    }

    private function eagerLoadDatatableDisplayPosition(Builder|Relation $positions, ?UserPosition $actor): void
    {
        $query = $positions instanceof Relation ? $positions->getQuery() : $positions;

        $this->userManagementAccessService->applyVisiblePositionsScope($query, $actor);

        $query->with([
            'jabatan:id,nama',
            'instansi:id,nama',
            'unitKerja:id,nama',
        ]);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function allowedStringFilter(Request $request, string $key, array $allowed): ?string
    {
        $value = trim((string) $request->input($key, ''));

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function decodedIdFilter(Request $request, string $key): ?int
    {
        $value = trim((string) $request->input($key, ''));

        if ($value === '') {
            return null;
        }

        return EncryptedId::tryDecode($value);
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

    /**
     * @return array<string, string>
     */
    private function editableUserAccountStatuses(): array
    {
        return [
            User::STATUS_ACTIVE => 'Aktif',
            User::STATUS_PENDING => 'Pending',
            User::STATUS_INACTIVE => 'Nonaktif',
            User::STATUS_SUSPENDED => 'Suspended',
        ];
    }

    private function managementDeniedResponse(
        Request $request,
        string $message,
        string $eventType,
        ?User $targetUser = null
    ) {
        $actor = $this->activePositionService->managementActor();

        $this->userManagementAuditLogger->blocked($eventType, [
            'actor_user' => $request->user(),
            'actor_position' => $actor,
            'target_user' => $targetUser,
            'resource_type' => $targetUser instanceof User ? User::class : 'management_users',
            'resource_id' => $targetUser?->getKey(),
            'before_state' => $targetUser instanceof User
                ? $this->userManagementAuditLogger->userSnapshot($targetUser)
                : null,
            'reason_code' => 'unauthorized_scope',
            'reason' => $message,
            'message' => $message,
            'metadata' => [
                'requested_action' => $eventType,
                'actor_is_full_admin' => $this->userManagementAccessService->isFullAdmin($actor),
                'actor_can_manage_target' => $targetUser instanceof User
                    && $this->userManagementAccessService->canManageUser($targetUser, $actor),
            ],
        ], $request);

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
