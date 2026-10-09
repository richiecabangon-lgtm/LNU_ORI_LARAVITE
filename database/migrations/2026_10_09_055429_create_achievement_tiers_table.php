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
        Schema::create('achievement_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->decimal('minimum_points', 10, 2)->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('achievement_tiers')->insert(collect([
            ['Emerging Scholar', 0],
            ['Developing Scholar', 150],
            ['Established Scholar', 300],
            ['Distinguished Scholar', 600],
            ['Research Leader', 1000],
        ])->map(fn (array $tier): array => [
            'title' => $tier[0],
            'minimum_points' => $tier[1],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievement_tiers');
    }
};
