<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fpes_report_settings', function (Blueprint $table): void {
            $table->string('university_logo_path')->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fpes_report_settings', function (Blueprint $table): void {
            $table->dropColumn('university_logo_path');
        });
    }
};
