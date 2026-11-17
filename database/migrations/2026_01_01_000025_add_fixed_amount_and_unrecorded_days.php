<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_deduction_rules', function (Blueprint $table) {
            $table->decimal('fixed_amount', 15, 2)->default(0)->after('deduction_percent');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->unsignedSmallInteger('unrecorded_days')->default(0)->after('sick_days');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('unrecorded_days');
        });

        Schema::table('salary_deduction_rules', function (Blueprint $table) {
            $table->dropColumn('fixed_amount');
        });
    }
};
