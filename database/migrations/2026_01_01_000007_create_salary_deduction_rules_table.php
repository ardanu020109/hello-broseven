<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('status_key', 20)->unique(); // present, late, absent, leave, sick
            $table->string('name', 100);
            $table->decimal('deduction_percent', 5, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_deduction_rules');
    }
};
