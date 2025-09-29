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
        Schema::create('approves', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('description',500)->nullable();
            $table->smallInteger('status')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->bigInteger('amount')->nullable();
            $table->foreignId('minute_id')->constrained('minutes')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approves');
    }
};
