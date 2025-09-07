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
        Schema::create('minutes_titleholder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_id')->constrained('minutes')->cascadeOnDelete();
            $table->foreignId('titleholder_id')->constrained('titleholders')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minutes_titleholder');
    }
};
