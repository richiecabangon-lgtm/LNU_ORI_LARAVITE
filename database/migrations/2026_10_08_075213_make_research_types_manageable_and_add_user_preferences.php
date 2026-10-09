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
        Schema::create('research_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $labels = [
            'journal_article' => 'Journal Article',
            'conference_paper' => 'Conference Paper',
            'book_chapter' => 'Book Chapter',
            'book' => 'Book',
            'research_project' => 'Research Project',
            'creative_work' => 'Creative Work',
            'patent' => 'Patent / Intellectual Property',
            'extension' => 'Extension / Innovation Output',
            'other' => 'Other',
        ];

        foreach ($labels as $order => $label) {
            DB::table('research_types')->insert([
                'slug' => $order,
                'name' => $label,
                'is_active' => true,
                'sort_order' => array_search($order, array_keys($labels), true),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('research_type_point_settings')->insertOrIgnore([
                'research_type' => $order,
                'points_per_output' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile_photo_path')->nullable();
            $table->string('theme_preference', 10)->default('light');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['profile_photo_path', 'theme_preference']);
        });

        Schema::dropIfExists('research_types');
    }
};
