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
        Schema::table('users', function (Blueprint $table) {
            $table->json('research_interests')->nullable();
        });
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->json('research_interests')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('research_interests');
        });
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn('research_interests');
        });
    }
};
