<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InstansiSeeder extends Seeder
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
                'kode' => 'SKPD',
                'nama' => 'SKPD',
                'nama_singkat' => 'SKPD',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'DIKBUD',
                'nama' => 'DIKBUD',
                'nama_singkat' => 'DIKBUD',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'DINKES',
                'nama' => 'DINKES',
                'nama_singkat' => 'DINKES',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'SETDA',
                'nama' => 'SETDA',
                'nama_singkat' => 'SETDA',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'KEC_LOWOKWARU',
                'nama' => 'Kecamatan Lowokwaru',
                'nama_singkat' => 'Kec. Lowokwaru',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'KEC_KLOJEN',
                'nama' => 'Kecamatan Klojen',
                'nama_singkat' => 'Kec. Klojen',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'KEC_BLIMBING',
                'nama' => 'Kecamatan Blimbing',
                'nama_singkat' => 'Kec. Blimbing',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'KEC_SUKUN',
                'nama' => 'Kecamatan Sukun',
                'nama_singkat' => 'Kec. Sukun',
                'deskripsi' => null,
                'is_active' => true,
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
                'kode' => 'KEC_KEDUNGKANDANG',
                'nama' => 'Kecamatan Kedungkandang',
                'nama_singkat' => 'Kec. Kedungkandang',
                'deskripsi' => null,
                'is_active' => true,
                'sort_order' => 90,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'deleted_by_user_id' => null,
                'created_at' => '2026-04-01 23:54:37',
                'updated_at' => '2026-04-01 23:54:37',
                'deleted_at' => null,
            ],
        ];

        DB::transaction(function () use ($rows): void {
            $this->assertNoCanonicalConflicts($rows);

            DB::table('instansis')->insertOrIgnore($rows);
        });
    }

    /**
     * Abort instead of silently linking canonical codes/names to wrong IDs.
     */
    private function assertNoCanonicalConflicts(array $rows): void
    {
        $expectedById = collect($rows)->keyBy('id');

        $existingById = DB::table('instansis')
            ->whereIn('id', $expectedById->keys())
            ->get(['id', 'kode', 'nama']);

        foreach ($existingById as $existing) {
            $expected = $expectedById->get($existing->id);

            foreach (['kode', 'nama'] as $column) {
                if ((string) $existing->{$column} !== (string) $expected[$column]) {
                    throw new RuntimeException(sprintf(
                        'instansis ID %s conflicts on %s: database="%s", seed="%s".',
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

            $existingByValue = DB::table('instansis')
                ->whereIn($column, $expectedByValue->keys())
                ->get(['id', $column]);

            foreach ($existingByValue as $existing) {
                $expected = $expectedByValue->get($existing->{$column});

                if ((int) $existing->id !== (int) $expected['id']) {
                    throw new RuntimeException(sprintf(
                        'instansis.%s "%s" is already attached to ID %s; canonical ID is %s.',
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
