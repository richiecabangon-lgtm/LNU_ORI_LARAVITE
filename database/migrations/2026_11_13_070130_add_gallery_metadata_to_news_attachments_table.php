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
        Schema::table('news_attachments', function (Blueprint $table) {
            $table->boolean('is_gallery')->default(false);
            $table->string('alt_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_attachments', function (Blueprint $table) {
            $table->dropColumn(['is_gallery', 'alt_text']);
        });
    }
};
