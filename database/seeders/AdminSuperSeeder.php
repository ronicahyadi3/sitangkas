<?php

namespace Database\Seeders;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSuperSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $adminSuperJabatan = $this->adminSuperJabatan();
            $defaultInstansi = $this->defaultInstansi();
            $defaultUnitKerja = $this->defaultUnitKerja($defaultInstansi);

            $adminSuper = User::withTrashed()
                ->where('nik', '3513170305950003')
                ->first();

            if (! $adminSuper instanceof User) {
                $adminSuper = new User(['nik' => '3513170305950003']);
            }

            $adminSuper->forceFill([
                'nip' => '3513170305950003',
                'nama' => 'Admin Super SITANGKAS',
                'email' => null,
                'account_type' => User::ACCOUNT_TYPE_PERSONAL,
                'status' => User::STATUS_ACTIVE,
                'status_changed_at' => now(),
                'status_reason' => 'Seeded admin super account.',
                'password' => Hash::make('Password123!@#'),
                'password_changed_at' => now(),
                'password_expires_at' => null,
                'must_change_password' => false,
                'consecutive_failed_login_count' => 0,
                'last_failed_login_at' => null,
                'locked_at' => null,
                'locked_until' => null,
                'lock_reason' => null,
                'identity_verified_at' => now(),
                'source_system' => 'seeder',
                'external_id' => 'admin-super-3513170305950003',
                'last_synced_at' => now(),
                'tahun_aktif' => (int) now()->year,
                'deleted_at' => null,
            ])->save();

            if ($adminSuper->trashed()) {
                $adminSuper->restore();
            }

            $adminSuperPosition = UserPosition::withTrashed()
                ->where('user_id', $adminSuper->getKey())
                ->where('jabatan_id', $adminSuperJabatan->getKey())
                ->where('instansi_id', $defaultInstansi->getKey())
                ->where('unit_kerja_id', $defaultUnitKerja->getKey())
                ->first();

            if (! $adminSuperPosition instanceof UserPosition) {
                $adminSuperPosition = new UserPosition([
                    'user_id' => $adminSuper->getKey(),
                    'jabatan_id' => $adminSuperJabatan->getKey(),
                    'instansi_id' => $defaultInstansi->getKey(),
                    'unit_kerja_id' => $defaultUnitKerja->getKey(),
                ]);
            }

            $adminSuperPosition->forceFill([
                'is_active' => true,
                'started_at' => today(),
                'ended_at' => null,
                'last_used_at' => now(),
                'activated_at' => now(),
                'activated_by_user_id' => $adminSuper->getKey(),
                'deactivated_at' => null,
                'deactivated_by_user_id' => null,
                'deactivation_reason' => null,
                'notes' => 'Default Admin Super position seeded for initial installation.',
                'source_system' => 'seeder',
                'external_id' => 'admin-super-position-3513170305950003',
                'last_synced_at' => now(),
                'created_by_user_id' => $adminSuper->getKey(),
                'updated_by_user_id' => $adminSuper->getKey(),
                'deleted_at' => null,
            ])->save();

            if ($adminSuperPosition->trashed()) {
                $adminSuperPosition->restore();
            }
        });
    }

    private function adminSuperJabatan(): Jabatan
    {
        $jabatan = Jabatan::query()
            ->where('kode', 'ADMIN_SUPER')
            ->first();

        if (! $jabatan instanceof Jabatan) {
            throw new RuntimeException('Jabatan ADMIN_SUPER belum tersedia. Jalankan JabatanSeeder lebih dulu.');
        }

        return $jabatan;
    }

    private function defaultInstansi(): Instansi
    {
        $instansi = Instansi::query()
            ->where('kode', 'SKPD')
            ->first();

        if (! $instansi instanceof Instansi) {
            throw new RuntimeException('Instansi SKPD belum tersedia. Jalankan InstansiSeeder lebih dulu.');
        }

        return $instansi;
    }

    private function defaultUnitKerja(Instansi $instansi): UnitKerja
    {
        $unitKerja = UnitKerja::query()
            ->whereBelongsTo($instansi)
            ->active()
            ->effective()
            ->ordered()
            ->first();

        if (! $unitKerja instanceof UnitKerja) {
            throw new RuntimeException('Unit kerja aktif untuk instansi SKPD belum tersedia. Jalankan UnitKerjaSeeder lebih dulu.');
        }

        return $unitKerja;
    }
}
