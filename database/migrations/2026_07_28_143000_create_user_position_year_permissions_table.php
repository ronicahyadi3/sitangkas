<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_position_year_permissions', function (Blueprint $table) {
            $table->id();

            /*
             * Posisi pengguna yang menerima pengecualian akses.
             *
             * Tabel ini hanya menyimpan izin tambahan untuk membuka akses tulis
             * pada tahun historis. Tahun berjalan tidak memerlukan record di sini
             * selama posisi aktif dan role/jabatan memang memiliki izin terhadap
             * aksi yang diminta.
             */
            $table->foreignId('user_position_id');

            /*
             * Tahun anggaran/data yang dibuka.
             * Menggunakan SMALLINT agar lebih ringkas daripada INT.
             */
            $table->unsignedSmallInteger('tahun');

            /*
             * historical_write:
             * membuka kunci read-only tahun historis.
             *
             * Hak aksi sebenarnya (create/update/delete/verify/sign, dan lain-lain)
             * tetap berasal dari role/jabatan. Permission ini tidak boleh
             * memperluas kewenangan role.
             */
            $table->string('permission_type', 30)
                ->default('historical_write');

            /*
             * pending  : permohonan tercatat, belum disetujui
             * active   : izin dapat digunakan
             * revoked  : izin dicabut
             * expired  : masa izin berakhir
             * rejected : permohonan ditolak
             * cancelled: permohonan dibatalkan
             */
            $table->string('status', 20)
                ->default('pending');

            /*
             * Masa berlaku izin. valid_from boleh NULL dan diperlakukan sama
             * dengan granted_at. valid_until NULL berarti berlaku sampai dicabut.
             */
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            /*
             * Dasar permintaan/izin. Aplikasi sebaiknya mewajibkan reason pada
             * saat meminta atau memberikan izin tahun historis.
             */
            $table->text('reason')->nullable();
            $table->string('reference_number', 150)->nullable();
            $table->date('reference_date')->nullable();

            /*
             * Audit permohonan.
             */
            $table->foreignId('requested_by_user_id')->nullable();
            $table->foreignId('requested_by_position_id')->nullable();
            $table->timestamp('requested_at')->nullable();

            /*
             * Audit pemberian izin. Simpan user dan posisi pemberi izin agar
             * kapasitas/jabatan aktor pada saat keputusan tetap dapat diketahui.
             */
            $table->foreignId('granted_by_user_id')->nullable();
            $table->foreignId('granted_by_position_id')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->text('grant_notes')->nullable();

            /*
             * Audit pencabutan izin.
             */
            $table->foreignId('revoked_by_user_id')->nullable();
            $table->foreignId('revoked_by_position_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();

            /*
             * Ringkasan penggunaan untuk dashboard/query cepat.
             * Detail setiap penggunaan disimpan append-only pada
             * user_position_year_permission_events.
             */
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('last_used_by_user_id')->nullable();
            $table->foreignId('last_used_by_position_id')->nullable();

            /*
             * Audit pengelolaan record. Histori detail tetap berada pada
             * tabel event; kolom ini hanya menyimpan aktor terakhir.
             */
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();

            $table->timestamps();

            /*
             * Marker bernilai 1 hanya untuk status active.
             * Unique index di bawah mencegah dua izin aktif untuk posisi,
             * tahun, dan tipe permission yang sama, tetapi tetap mengizinkan
             * beberapa record historis yang sudah revoked/expired/rejected.
             */
            $table->unsignedTinyInteger('active_unique_marker')
                ->storedAs("CASE WHEN status = 'active' THEN 1 ELSE NULL END");

            $table->unique(
                ['user_position_id', 'tahun', 'permission_type', 'active_unique_marker'],
                'uq_upyp_active_permission'
            );

            /*
             * Query otorisasi utama:
             * posisi aktif + tahun target + status.
             */
            $table->index(
                ['user_position_id', 'tahun', 'status'],
                'ix_upyp_position_year_status'
            );

            /*
             * Monitoring seluruh izin tahun tertentu, termasuk izin
             * yang akan kedaluwarsa.
             */
            $table->index(
                ['tahun', 'status', 'valid_until'],
                'ix_upyp_year_status_expiry'
            );

            $table->index(
                ['granted_by_user_id', 'granted_at'],
                'ix_upyp_granter_time'
            );

            $table->index(
                'last_used_at',
                'ix_upyp_last_used'
            );

            $table->foreign('user_position_id', 'fk_upyp_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('requested_by_user_id', 'fk_upyp_requested_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('requested_by_position_id', 'fk_upyp_requested_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('granted_by_user_id', 'fk_upyp_granted_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('granted_by_position_id', 'fk_upyp_granted_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('revoked_by_user_id', 'fk_upyp_revoked_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('revoked_by_position_id', 'fk_upyp_revoked_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('last_used_by_user_id', 'fk_upyp_last_used_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('last_used_by_position_id', 'fk_upyp_last_used_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('created_by_user_id', 'fk_upyp_created_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('updated_by_user_id', 'fk_upyp_updated_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_year_permissions
            ADD CONSTRAINT chk_upyp_year
            CHECK (tahun BETWEEN 2000 AND 2200)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_year_permissions
            ADD CONSTRAINT chk_upyp_valid_period
            CHECK (
                valid_until IS NULL
                OR valid_from IS NULL
                OR valid_until >= valid_from
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_year_permissions
            ADD CONSTRAINT chk_upyp_status
            CHECK (
                status IN (
                    'pending',
                    'active',
                    'revoked',
                    'expired',
                    'rejected',
                    'cancelled'
                )
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_year_permissions
            ADD CONSTRAINT chk_upyp_permission_type
            CHECK (
                permission_type IN ('historical_write')
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_position_year_permissions');
    }
};
