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
            $table->string('orcid_url')->nullable();
            $table->string('google_scholar_url')->nullable();
            $table->string('institutional_profile_url')->nullable();
        });

        Schema::create('fpes_report_settings', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name')->default('Leyte Normal University');
            $table->string('office_name')->default('Office of Research and Innovation');
            $table->string('report_heading')->default('Faculty Performance Evaluation Sheet');
            $table->string('report_subheading')->nullable();
            $table->string('prepared_by_title')->default('Prepared by');
            $table->string('prepared_by_name')->nullable();
            $table->string('reviewed_by_title')->default('Reviewed by');
            $table->string('reviewed_by_name')->nullable();
            $table->string('approved_by_title')->default('Approved by');
            $table->string('approved_by_name')->nullable();
            $table->text('footer_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fpes_report_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['orcid_url', 'google_scholar_url', 'institutional_profile_url']);
        });
    }
};
