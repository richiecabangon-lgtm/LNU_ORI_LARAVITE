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
        Schema::table('news_posts', function (Blueprint $table) {
            $table->string('category', 50)->default('Announcement')->after('title');
            $table->string('cover_image_path')->nullable()->after('body');
            $table->string('cover_image_alt')->nullable()->after('cover_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->dropColumn(['category', 'cover_image_path', 'cover_image_alt']);
        });
    }
};
