<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class JabatanSeeder extends Seeder
{
    /**
     * Seed canonical SITANGKAS master data extracted from
     * dump-sitangkas_old-202607281603.sql.
     *
     * Legacy numeric IDs are intentionally retained to simplify migration
     * of user_positions. Application business rules must still use `kode`.
     */
    public function run(): void
    {
        $rows = [
            [
                'id' => 1,
                'kode' => 'ADMIN_SUPER',
                'nama' => 'Admin Super',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 10,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'kode' => 'BUD',
                'nama' => 'BUD',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 20,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 3,
                'kode' => 'KUASA_BUD',
                'nama' => 'Kuasa BUD',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 30,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 4,
                'kode' => 'VERIFIKATOR_BUD',
                'nama' => 'Verifikator BUD',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 40,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 5,
                'kode' => 'PA',
                'nama' => 'PA',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 50,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 6,
                'kode' => 'KPA',
                'nama' => 'KPA',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 60,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 7,
                'kode' => 'PPK_SKPD',
                'nama' => 'PPK-SKPD',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 70,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 8,
                'kode' => 'PPTK',
                'nama' => 'PPTK',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 80,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 9,
                'kode' => 'BP',
                'nama' => 'BP',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 90,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 10,
                'kode' => 'BPP',
                'nama' => 'BPP',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 100,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 11,
                'kode' => 'BANK',
                'nama' => 'BANK',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 110,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
            [
                'id' => 12,
                'kode' => 'PIMPINAN',
                'nama' => 'Pimpinan',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 120,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-13 02:27:22',
                'updated_at' => '2026-04-13 02:27:22',
                'deleted_at' => null,
            ],
            [
                'id' => 13,
                'kode' => 'AUDITOR',
                'nama' => 'Auditor',
                'deskripsi' => null,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 130,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-13 19:51:08',
                'updated_at' => '2026-04-13 19:51:08',
                'deleted_at' => null,
            ],
        ];

        DB::transaction(function () use ($rows): void {
            $this->assertNoCanonicalConflicts($rows);

            DB::table('jabatans')->insertOrIgnore($rows);
        });
    }

    /**
     * Abort instead of silently linking canonical codes/names to wrong IDs.
     */
    private function assertNoCanonicalConflicts(array $rows): void
    {
        $expectedById = collect($rows)->keyBy('id');

        $existingById = DB::table('jabatans')
            ->whereIn('id', $expectedById->keys())
            ->get(['id', 'kode', 'nama']);

        foreach ($existingById as $existing) {
            $expected = $expectedById->get($existing->id);

            foreach (['kode', 'nama'] as $column) {
                if ((string) $existing->{$column} !== (string) $expected[$column]) {
                    throw new RuntimeException(sprintf(
                        'jabatans ID %s conflicts on %s: database="%s", seed="%s".',
                        $existing->id,
                        $column,
                        $existing->{$column},
                        $expected[$column],
                    ));
                }
            }
        }

        foreach (['kode', 'nama'] as $column) {
            $expectedByValue = collect($rows)->keyBy($column);

            $existingByValue = DB::table('jabatans')
                ->whereIn($column, $expectedByValue->keys())
                ->get(['id', $column]);

            foreach ($existingByValue as $existing) {
                $expected = $expectedByValue->get($existing->{$column});

                if ((int) $existing->id !== (int) $expected['id']) {
                    throw new RuntimeException(sprintf(
                        'jabatans.%s "%s" is already attached to ID %s; canonical ID is %s.',
                        $column,
                        $existing->{$column},
                        $existing->id,
                        $expected['id'],
                    ));
                }
            }
        }
    }
}
