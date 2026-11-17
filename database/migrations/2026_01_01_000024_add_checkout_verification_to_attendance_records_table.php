<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->decimal('check_out_face_score', 5, 4)->nullable()->after('face_match_score');
            $table->string('check_out_method', 30)->nullable()->after('verification_method');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['check_out_face_score', 'check_out_method']);
        });
    }
};
