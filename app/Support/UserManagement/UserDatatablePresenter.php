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
        $activePositionsCount = (int) ($user->active_positions_count ?? 0);
        $positionSummary = match (true) {
            $positionsCount === 0 => 'Belum memiliki posisi',
            $activePositionsCount === 0 => 'Belum memiliki posisi aktif',
            default => $activePositionsCount.' aktif dari '.$positionsCount.' posisi',
        };

        return '
            <div class="users-table-cell">
                <div class="users-table-cell__title">'.e($user->nama).'</div>
                <div class="users-table-cell__meta">'.e($positionSummary).'</div>
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

    public function securityStatusColumn(User $user): string
    {
        return '
            <div class="users-badge-stack">
                '.$this->accountSecurityBadge($user).'
                '.$this->passwordSecurityBadge($user).'
                '.$this->mfaSecurityBadge($user).'
            </div>
        ';
    }

    public function usedPositionColumn(User $user): string
    {
        $position = $user->managementDisplayUserPosition();

        if (! $position instanceof UserPosition) {
            return '
                <div class="users-badge-stack">
                    <span class="users-position-pill users-position-pill--missing"><i class="fa fa-triangle-exclamation"></i>Tidak ada posisi aktif</span>
                </div>
            ';
        }

        $jabatanName = $this->loadedRelationName($position, 'jabatan') ?? 'Jabatan tidak tersedia';
        $unitKerjaName = $this->loadedRelationName($position, 'unitKerja');
        $instansiName = $this->loadedRelationName($position, 'instansi');
        $scopeText = implode(' - ', array_values(array_filter([$unitKerjaName, $instansiName])));

        return '
            <div class="users-table-cell">
                <div class="users-table-cell__title">'.e($jabatanName).'</div>
                <div class="users-table-cell__meta">'.e($scopeText !== '' ? $scopeText : 'Scope posisi belum tersedia').'</div>
                <div class="users-badge-stack mt-1">
                    '.$this->positionUsageBadge($position).'
                    '.$this->positionAvailabilityBadge($position).'
                </div>
            </div>
        ';
    }

    public function positionsCountColumn(User $user): string
    {
        $positionsCount = (int) $user->positions_count;
        $activePositionsCount = (int) ($user->active_positions_count ?? 0);

        if ($positionsCount === 0) {
            return '
                <div class="users-badge-stack users-badge-stack--center">
                    <span class="users-position-pill users-position-pill--missing"><i class="fa fa-triangle-exclamation"></i>Belum ada posisi</span>
                </div>
            ';
        }

        return '
            <div class="users-badge-stack users-badge-stack--center">
                <span class="users-position-pill users-position-pill--'.($activePositionsCount > 0 ? 'active' : 'missing').'"><i class="fa '.($activePositionsCount > 0 ? 'fa-briefcase' : 'fa-triangle-exclamation').'"></i>'.($activePositionsCount > 0 ? $activePositionsCount.' aktif' : 'Tidak ada aktif').'</span>
                <span class="users-position-pill users-position-pill--history"><i class="fa fa-layer-group"></i>'.$positionsCount.' total</span>
            </div>
        ';
    }

    public function actionsColumn(User $user, ?UserPosition $actor): string
    {
        $canEditProfile = $this->userManagementAccessService->canEditUserProfile($user, $actor);
        $canDeleteProfile = $this->userManagementAccessService->canManageUser($user, $actor);
        $canManagePositions = $this->userManagementAccessService->canViewUser($user, $actor);
        $canManageAccountSecurity = $this->userManagementAccessService->isFullAdmin($actor) && $canDeleteProfile;
        $payloadJson = e((string) json_encode($this->payload($user) + [
            'can_edit_profile' => $canEditProfile,
            'can_delete_profile' => $canDeleteProfile,
            'can_manage_positions' => $canManagePositions,
            'can_manage_security' => $canManageAccountSecurity,
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

    private function accountSecurityBadge(User $user): string
    {
        if ($user->isLocked()) {
            return '<span class="users-security-badge users-security-badge--locked"><i class="fa fa-lock"></i>Terkunci</span>';
        }

        return '<span class="users-security-badge users-security-badge--ok"><i class="fa fa-circle-check"></i>Akun OK</span>';
    }

    private function passwordSecurityBadge(User $user): string
    {
        if ($user->requiresPasswordChange()) {
            return '<span class="users-security-badge users-security-badge--password-required"><i class="fa fa-key"></i>Wajib ganti</span>';
        }

        return '<span class="users-security-badge users-security-badge--password-ok"><i class="fa fa-key"></i>Password OK</span>';
    }

    private function mfaSecurityBadge(User $user): string
    {
        if ($this->hasActiveMfa($user)) {
            return '<span class="users-security-badge users-security-badge--mfa-active"><i class="fa fa-shield-halved"></i>MFA Aktif</span>';
        }

        if ($this->hasPendingMfa($user)) {
            return '<span class="users-security-badge users-security-badge--mfa-pending"><i class="fa fa-clock"></i>MFA Pending</span>';
        }

        return '<span class="users-security-badge users-security-badge--mfa-missing"><i class="fa fa-shield-halved"></i>Belum MFA</span>';
    }

    private function hasActiveMfa(User $user): bool
    {
        return $this->hasRawEncryptedValue($user, 'mfa_secret')
            && $user->mfa_enabled_at !== null
            && $user->mfa_confirmed_at !== null;
    }

    private function hasPendingMfa(User $user): bool
    {
        return $this->hasRawEncryptedValue($user, 'mfa_pending_secret')
            && $user->mfa_pending_secret_created_at !== null;
    }

    private function hasRawEncryptedValue(User $user, string $key): bool
    {
        $value = $user->getRawOriginal($key);

        return is_string($value) && trim($value) !== '';
    }

    private function loadedRelationName(UserPosition $position, string $relation): ?string
    {
        if (! $position->relationLoaded($relation)) {
            return null;
        }

        $relatedModel = $position->getRelation($relation);

        if (! is_object($relatedModel) || ! isset($relatedModel->nama)) {
            return null;
        }

        $name = $relatedModel->nama;

        return is_string($name) && trim($name) !== '' ? $name : null;
    }

    private function positionUsageBadge(UserPosition $position): string
    {
        if ($position->last_used_at !== null) {
            return '<span class="users-position-pill users-position-pill--used"><i class="fa fa-clock"></i>Dipakai '.$position->last_used_at->format('d/m/Y H:i').'</span>';
        }

        return '<span class="users-position-pill users-position-pill--unused"><i class="fa fa-circle-info"></i>Belum pernah dipakai</span>';
    }

    private function positionAvailabilityBadge(UserPosition $position): string
    {
        if ($position->isAvailableForSelection()) {
            return '';
        }

        return '<span class="users-position-pill users-position-pill--inactive"><i class="fa fa-circle-minus"></i>Posisi nonaktif</span>';
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
