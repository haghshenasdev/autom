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
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('subject')->index();
            $table->string('description')->nullable();
            $table->string('file')->nullable();
            $table->foreignId('type_id')->nullable()->constrained('types')->nullOnDelete();
            $table->mediumInteger('status')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('titleholder_id')->nullable()->constrained('titleholders')->nullOnDelete();
            $table->foreignId('peiroow_letter_id')->nullable()->constrained('letters')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
