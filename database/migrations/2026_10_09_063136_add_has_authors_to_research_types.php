<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Categories where the faculty member participates in a role instead of authoring the work. */
    private const ROLE_BASED_CATEGORIES = [
        'Research-related activities',
        'Refereeing in a peer-reviewed journal',
        'Involvement in completed student theses',
        'Involvement in proposal hearing',
        'Funded research involvement',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('research_types', function (Blueprint $table) {
            $table->boolean('has_authors')->default(true)->after('category');
        });

        DB::table('research_types')->whereIn('category', self::ROLE_BASED_CATEGORIES)->update(['has_authors' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_types', function (Blueprint $table) {
            $table->dropColumn('has_authors');
        });
    }
};
