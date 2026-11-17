<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('employee_number', 30)->unique(); // NIK internal
            $table->string('full_name');
            $table->string('national_id', 20)->nullable()->unique(); // NIK KTP
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('photo')->nullable();

            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('join_date');
            $table->boolean('is_active')->default(true)->index();
            $table->enum('work_mode', ['WFO', 'WFH', 'Hybrid'])->default('WFO');

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();

            $table->string('ktp_photo')->nullable();
            $table->string('supporting_document')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
