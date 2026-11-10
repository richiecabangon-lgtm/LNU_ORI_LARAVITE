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
        Schema::create('certification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name');
            $table->string('office_name');
            $table->string('heading');
            $table->text('additional_text')->nullable();
            $table->string('first_signatory_name')->nullable();
            $table->string('first_signatory_title')->nullable();
            $table->string('second_signatory_name')->nullable();
            $table->string('second_signatory_title')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_settings');
    }
};
