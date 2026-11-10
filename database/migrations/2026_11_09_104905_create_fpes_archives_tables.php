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
        Schema::create('fpes_archive_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('version')->default(0);
        });
        DB::table('fpes_archive_locks')->insert(['id' => 1, 'version' => 0]);

        Schema::create('fpes_archives', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('fpes_archive_submissions', function (Blueprint $table) {
            $table->foreignId('fpes_archive_id')->constrained('fpes_archives');
            $table->foreignId('research_submission_id')->constrained('research_submissions');
            $table->primary(['fpes_archive_id', 'research_submission_id']);
            $table->index('research_submission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fpes_archive_submissions');
        Schema::dropIfExists('fpes_archives');
        Schema::dropIfExists('fpes_archive_locks');
    }
};
