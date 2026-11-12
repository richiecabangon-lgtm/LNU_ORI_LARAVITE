<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('research_types', function (Blueprint $table) {
            $table->string('unit', 10)->default('output');
            $table->decimal('semester_ceiling', 8, 2)->nullable();
        });
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('unit_snapshot', 10)->nullable();
            $table->decimal('rate_snapshot', 8, 2)->nullable();
        });

        $catalogue = [
            ['Research-related activities', 'Attendance - Unit/Department activity (per hour)', 'hour', null],
            ['Research-related activities', 'Attendance - College-wide (per hour)', 'hour', null],
            ['Research-related activities', 'Attendance - University-wide (per hour)', 'hour', null],
            ['Research-related activities', 'Attendance - Regional/National (per day)', 'day', null],
            ['Research-related activities', 'Attendance - International (per day)', 'day', null],
            ['Funded research involvement', 'Program/project leader (per hour)', 'hour', 40],
            ['Funded research involvement', 'Program/project member (per hour)', 'hour', 30],
            ['Funded research involvement', 'Data gatherer / enumerator (per hour)', 'hour', 40],
            ['Funded research involvement', 'Data encoder / tabulator (per hour)', 'hour', 40],
            ['Funded research involvement', 'Compiler / binder / collator (per hour)', 'hour', 40],
        ];

        foreach ($catalogue as [$category, $name, $unit, $ceiling]) {
            $slug = Str::slug($category.' '.$name, '_');
            if (strlen($slug) > 80) {
                $slug = substr($slug, 0, 69).'_'.hash('crc32b', $slug);
            }
            DB::table('research_types')->where('slug', $slug)->update(['unit' => $unit, 'semester_ceiling' => $ceiling]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_submissions', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_snapshot', 'rate_snapshot']);
        });
        Schema::table('research_types', function (Blueprint $table) {
            $table->dropColumn(['unit', 'semester_ceiling']);
        });
    }
};
