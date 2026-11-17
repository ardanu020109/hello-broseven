<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->enum('status', ['present', 'late', 'absent', 'leave'])->index();
            $table->enum('work_mode', ['WFO', 'WFH'])->default('WFO');
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('verification_method', 30)->nullable(); // face_gps, face, system, manual
            $table->decimal('face_match_score', 5, 4)->nullable();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();

            $table->boolean('is_corrected')->default(false);
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->text('correction_reason')->nullable();

            $table->timestamps();

            // satu record per karyawan per hari: mencegah penghitungan ganda
            $table->unique(['employee_id', 'attendance_date']);
            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
