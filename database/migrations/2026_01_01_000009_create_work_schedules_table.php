<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('check_in_time');
            $table->time('check_out_time');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->boolean('is_shift')->default(false);
            $table->string('timezone', 50)->default('Asia/Makassar');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
