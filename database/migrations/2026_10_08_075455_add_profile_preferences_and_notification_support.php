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
        Schema::create('sdgs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $sdgs = [
            'No Poverty', 'Zero Hunger', 'Good Health and Well-Being', 'Quality Education',
            'Gender Equality', 'Clean Water and Sanitation', 'Affordable and Clean Energy',
            'Decent Work and Economic Growth', 'Industry, Innovation and Infrastructure',
            'Reduced Inequalities', 'Sustainable Cities and Communities', 'Responsible Consumption and Production',
            'Climate Action', 'Life Below Water', 'Life on Land', 'Peace, Justice and Strong Institutions',
            'Partnerships for the Goals',
        ];

        foreach ($sdgs as $index => $name) {
            DB::table('sdgs')->insert([
                'number' => $index + 1,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('research_agendas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('research_submission_sdg', function (Blueprint $table): void {
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sdg_id')->constrained()->cascadeOnDelete();
            $table->primary(['research_submission_id', 'sdg_id']);
        });

        Schema::create('research_agenda_submission', function (Blueprint $table): void {
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_agenda_id')->constrained()->cascadeOnDelete();
            $table->primary(['research_submission_id', 'research_agenda_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::table('fpes_report_settings', function (Blueprint $table): void {
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fpes_report_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prepared_by_user_id');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
        });

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('research_agenda_submission');
        Schema::dropIfExists('research_submission_sdg');
        Schema::dropIfExists('research_agendas');
        Schema::dropIfExists('sdgs');
    }
};
