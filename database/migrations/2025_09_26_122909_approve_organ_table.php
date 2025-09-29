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
        Schema::create('approve_organ', function (Blueprint $table) {
            $table->foreignId('approve_id')->constrained('approves')->onDelete('cascade');
            $table->foreignId('organ_id')->constrained('organs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approve_organ');
    }
};
