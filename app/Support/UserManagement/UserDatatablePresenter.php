<?php

namespace App\Support\UserManagement;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\User\UserManagementAccessService;

class UserDatatablePresenter
{
    public function __construct(
        private UserManagementAccessService $userManagementAccessService
    ) {}

    public function nikColumn(User $user): string
    {
        return '
            <div class="users-table-cell">
                <div class="users-table-cell__title font-monospace">'.e($user->nik).'</div>
                <div class="users-table-cell__meta">'.($user->nip ? 'NIP '.e($user->nip) : 'NIP belum diisi').'</div>
            </div>
        ';
    }

    public function nameColumn(User $user): string
    {
        $positionsCount = (int) $user->positions_count;

        return '
            <div class="users-table-cell">
                <div class="users-table-cell__title">'.e($user->nama).'</div>
                <div class="users-table-cell__meta">'.($positionsCount > 0 ? 'Sudah memiliki '.$positionsCount.' posisi' : 'Belum memiliki posisi').'</div>
            </div>
        ';
    }

    public function emailColumn(User $user): string
    {
        if (! $user->email) {
            return '<span class="users-muted-pill">Belum diisi</span>';
        }

        return '
            <div class="users-table-cell">
                <div class="users-table-cell__title">'.e($user->email).'</div>
                <div class="users-table-cell__meta">Email terdaftar</div>
            </div>
        ';
    }

    public function accountStatusColumn(User $user): string
    {
        return '
            <div class="users-badge-stack">
                '.$this->statusBadge($user).'
                '.$this->accountTypeBadge($user).'
                <span class="users-year-pill"><i class="fa fa-calendar-days"></i>'.($user->tahun_aktif ? e((string) $user->tahun_aktif) : 'Tahun belum diisi').'</span>
            </div>
        ';
    }

    public function positionsCountColumn(User $user): string
    {
        $positionsCount = (int) $user->positions_count;

        if ($positionsCount === 0) {
            return '
                <div class="users-badge-stack users-badge-stack--center">
                    <span class="users-position-pill users-position-pill--missing"><i class="fa fa-triangle-exclamation"></i>Belum ada posisi</span>
                </div>
            ';
        }

        return '
            <div class="users-badge-stack users-badge-stack--center">
                <span class="users-position-pill users-position-pill--active"><i class="fa fa-briefcase"></i>'.$positionsCount.' posisi</span>
            </div>
        ';
    }

    public function actionsColumn(User $user, ?UserPosition $actor): string
    {
        $canEditProfile = $this->userManagementAccessService->canEditUserProfile($user, $actor);
        $canDeleteProfile = $this->userManagementAccessService->canManageUser($user, $actor);
        $canManagePositions = $this->userManagementAccessService->canViewUser($user, $actor);
        $payloadJson = e((string) json_encode($this->payload($user) + [
            'can_edit_profile' => $canEditProfile,
            'can_delete_profile' => $canDeleteProfile,
            'can_manage_positions' => $canManagePositions,
            'can_manage_security' => $canDeleteProfile,
        ]));

        $editButton = $canEditProfile
            ? '<button type="button" class="dropdown-item btnEditUser" data-user=\''.$payloadJson.'\'>
                <i class="fa fa-pen"></i>Edit User
              </button>'
            : '<button type="button" class="dropdown-item disabled" disabled title="Profil global user ini hanya bisa diedit oleh Admin Super karena memiliki jabatan yang tidak boleh Anda kelola.">
                <i class="fa fa-pen"></i>Edit User
              </button>';

        $positionsButton = $canManagePositions
            ? '<button type="button" class="dropdown-item btnManagePos" data-user=\''.$payloadJson.'\'>
                <i class="fa fa-briefcase"></i>Kelola Posisi
              </button>'
            : '<button type="button" class="dropdown-item disabled" disabled>
                <i class="fa fa-briefcase"></i>Kelola Posisi
              </button>';

        $securityButton = '<button type="button" class="dropdown-item btnManageSecurity" data-user=\''.$payloadJson.'\'>
                <i class="fa fa-shield-halved"></i>Keamanan Akun
              </button>';

        $deleteButton = $canDeleteProfile
            ? '<button type="button" class="dropdown-item text-danger btnDeleteUser" data-user=\''.$payloadJson.'\'>
                <i class="fa fa-trash"></i>Hapus User
              </button>'
            : '<button type="button" class="dropdown-item disabled" disabled title="User ini hanya dapat dihapus bila seluruh posisinya masuk scope Anda.">
                <i class="fa fa-trash"></i>Hapus User
              </button>';

        return '
          <div class="dropdown users-action-dropdown">
            <button class="btn btn-sm btn-outline-secondary users-action-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi user">
                <i class="fa fa-ellipsis-vertical"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end users-action-menu">
                '.$editButton.'
                '.$positionsButton.'
                '.$securityButton.'
                <div class="dropdown-divider"></div>
                '.$deleteButton.'
            </div>
          </div>
        ';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user): array
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

    private function statusBadge(User $user): string
    {
        $status = (string) $user->status;
        $labels = $this->accountStatuses();

        $icons = [
            User::STATUS_ACTIVE => 'fa-circle-check',
            User::STATUS_PENDING => 'fa-clock',
            User::STATUS_INACTIVE => 'fa-circle-minus',
            User::STATUS_LOCKED => 'fa-lock',
            User::STATUS_SUSPENDED => 'fa-ban',
        ];

        return '<span class="users-account-badge users-account-badge--'.$this->badgeClassSuffix($status).'"><i class="fa '.($icons[$status] ?? 'fa-circle-info').'"></i>'.e($labels[$status] ?? $status).'</span>';
    }

    private function accountTypeBadge(User $user): string
    {
        $accountType = (string) $user->account_type;
        $labels = $this->accountTypes();

        return '<span class="users-type-badge users-type-badge--'.$this->badgeClassSuffix($accountType).'"><i class="fa fa-id-card"></i>'.e($labels[$accountType] ?? $accountType).'</span>';
    }

    /**
     * @return array<string, string>
     */
    private function accountTypes(): array
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
    private function accountStatuses(): array
    {
        return [
            User::STATUS_ACTIVE => 'Aktif',
            User::STATUS_PENDING => 'Pending',
            User::STATUS_INACTIVE => 'Nonaktif',
            User::STATUS_LOCKED => 'Terkunci',
            User::STATUS_SUSPENDED => 'Suspended',
        ];
    }

    private function badgeClassSuffix(string $value): string
    {
        return str_replace('_', '-', str($value)->slug()->toString());
    }
}
