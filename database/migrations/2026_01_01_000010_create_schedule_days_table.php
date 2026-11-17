<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // ISO: 1 = Senin ... 7 = Minggu
            $table->boolean('is_working_day')->default(true);
            $table->enum('work_mode', ['WFO', 'WFH'])->default('WFO'); // dipakai untuk karyawan Hybrid
            $table->timestamps();

            $table->unique(['work_schedule_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
    }
};
