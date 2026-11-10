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
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->string('funding_source', 20)->nullable();
            $table->string('funding_organization', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn(['funding_source', 'funding_organization']);
        });
    }
};
