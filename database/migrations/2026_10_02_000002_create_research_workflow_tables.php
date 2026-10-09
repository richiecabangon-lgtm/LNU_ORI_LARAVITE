<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_code')->unique();
            $table->foreignId('faculty_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('college_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('research_type');
            $table->text('abstract')->nullable();
            $table->json('authors');
            $table->string('venue')->nullable();
            $table->string('publisher')->nullable();
            $table->string('doi')->nullable();
            $table->string('issn_isbn')->nullable();
            $table->string('indexing')->nullable();
            $table->date('publication_date')->nullable();
            $table->unsignedTinyInteger('faculty_role_rank')->default(1);
            $table->decimal('claimed_points', 8, 2)->nullable();
            $table->text('faculty_remarks')->nullable();

            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['faculty_id', 'status']);
            $table->index(['academic_year_id', 'status']);
        });

        Schema::create('submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('category')->default('evidence'); // evidence, certificate, other
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedTinyInteger('quality_rating')->nullable();
            $table->unsignedTinyInteger('relevance_rating')->nullable();
            $table->unsignedTinyInteger('documentation_rating')->nullable();
            $table->string('recommendation');
            $table->decimal('awarded_points', 8, 2)->nullable();
            $table->text('remarks');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique('research_submission_id');
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision'); // approved, rejected
            $table->decimal('final_points', 8, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique('research_submission_id');
        });

        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['research_submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('submission_attachments');
        Schema::dropIfExists('research_submissions');
    }
};
