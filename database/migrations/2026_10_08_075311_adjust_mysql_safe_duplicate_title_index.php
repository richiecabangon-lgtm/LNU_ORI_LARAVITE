<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('research_submissions', function (Blueprint $table): void {
            $table->char('normalized_title_hash', 64)->nullable();
        });

        DB::table('research_submissions')->orderBy('id')->each(function (object $submission): void {
            $hash = hash('sha256', (string) $submission->normalized_title);
            if ($submission->deleted_at !== null) {
                $hash = hash('sha256', 'deleted|'.$submission->id);
            }

            DB::table('research_submissions')->where('id', $submission->id)->update([
                'normalized_title_hash' => $hash,
            ]);
        });

        Schema::table('research_submissions', function (Blueprint $table): void {
            $existingIndexes = collect(Schema::getIndexes('research_submissions'));
            if ($existingIndexes->contains('name', 'research_submissions_title_type_unique')) {
                $table->dropUnique('research_submissions_title_type_unique');
            }
            $table->unique(['normalized_title_hash', 'research_type'], 'research_submission_type_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table): void {
            $table->dropUnique('research_submission_type_hash_unique');
            $table->dropColumn('normalized_title_hash');
        });
    }
};
