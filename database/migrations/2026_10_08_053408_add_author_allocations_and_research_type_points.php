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
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->string('normalized_title', 500)->nullable();
            $table->index('normalized_title', 'rs_normalized_title_idx');
            $table->index(['normalized_title', 'research_type'], 'rs_title_type_lookup_idx');
        });

        Schema::create('research_type_point_settings', function (Blueprint $table) {
            $table->id();
            $table->string('research_type')->unique();
            $table->decimal('points_per_output', 8, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('research_submission_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('institution')->nullable();
            $table->unsignedTinyInteger('author_order');
            $table->decimal('allocated_points', 8, 2)->default(0);
            $table->string('authorization_disk')->nullable();
            $table->string('authorization_path')->nullable();
            $table->string('authorization_original_name')->nullable();
            $table->timestamps();

            $table->unique(['research_submission_id', 'author_order'], 'rsa_submission_order_uq');
            $table->index(['faculty_id', 'research_submission_id'], 'rsa_faculty_submission_idx');
        });

        $seenTitles = [];
        DB::table('research_submissions')->orderBy('id')->each(function (object $submission) use (&$seenTitles): void {
            $normalizedTitle = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $submission->title) ?? $submission->title));
            $fingerprint = $normalizedTitle.'|'.$submission->research_type;
            if ($submission->deleted_at !== null) {
                $normalizedTitle = '__deleted__'.$submission->id;
            } elseif (isset($seenTitles[$fingerprint])) {
                $normalizedTitle = '__legacy_duplicate__'.$submission->id;
            }
            if ($submission->deleted_at === null) {
                $seenTitles[$fingerprint] = true;
            }

            DB::table('research_submissions')->where('id', $submission->id)->update([
                'normalized_title' => $normalizedTitle,
            ]);

            $authors = json_decode($submission->authors, true);
            $authors = is_array($authors) && $authors !== [] ? $authors : ['Unknown author'];
            $share = round((float) ($submission->claimed_points ?? 0) / count($authors), 2);

            foreach (array_values($authors) as $index => $name) {
                $matchingFacultyIds = DB::table('users')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $name))])
                    ->where('role', 'faculty')
                    ->where('is_active', true)
                    ->limit(2)
                    ->pluck('id');
                $matchingFacultyId = $matchingFacultyIds->count() === 1 ? $matchingFacultyIds->first() : null;

                DB::table('research_submission_authors')->insert([
                    'research_submission_id' => $submission->id,
                    'faculty_id' => $matchingFacultyId ?? ($index === 0 ? $submission->faculty_id : null),
                    'name' => trim((string) $name) ?: 'Unknown author',
                    'author_order' => $index + 1,
                    'allocated_points' => $share,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropUnique('research_submissions_title_type_unique');
            $table->dropIndex(['normalized_title', 'research_type']);
            $table->dropIndex(['normalized_title']);
            $table->dropColumn('normalized_title');
        });

        Schema::dropIfExists('research_submission_authors');
        Schema::dropIfExists('research_type_point_settings');
    }
};
