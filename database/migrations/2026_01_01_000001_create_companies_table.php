<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('attendance_radius_meters')->default(100);
            $table->unsignedInteger('max_gps_accuracy_meters')->default(50);
            $table->decimal('face_match_threshold', 4, 3)->default(0.500); // jarak euclidean maks (lebih kecil = lebih ketat)
            $table->unsignedInteger('absent_cutoff_minutes')->default(120); // menit setelah jam pulang jadwal untuk menandai absent
            $table->string('timezone', 50)->default('Asia/Makassar');
            $table->time('operating_start')->nullable();
            $table->time('operating_end')->nullable();
            $table->string('payroll_deduction_basis', 30)->default('daily_salary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
