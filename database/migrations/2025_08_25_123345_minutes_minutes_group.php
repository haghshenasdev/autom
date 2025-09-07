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
        Schema::create('minutes_minutes_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_id')->constrained('minutes')->cascadeOnDelete();
            $table->foreignId('minutes_group_id')->constrained('minutes_groups')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minutes_minutes_group');
    }
};
