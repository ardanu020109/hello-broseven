<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dashboard dan laporan mengelompokkan absensi per tanggal dan status
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['attendance_date', 'status'], 'att_date_status_idx');
        });

        // Halaman Audit Log memfilter per aksi lalu mengurutkan waktu
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'audit_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_action_created_idx');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('att_date_status_idx');
        });
    }
};
