<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('status', 20); // present, late, absent, leave, sick
            $table->decimal('daily_salary', 15, 2);
            $table->decimal('deduction_percent', 5, 2)->default(0);
            $table->decimal('deduction_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'work_date']); // mencegah penghitungan ganda
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
