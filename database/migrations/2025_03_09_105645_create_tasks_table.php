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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->mediumInteger('status')->nullable();
            $table->mediumInteger('progress')->nullable();
            $table->text('description')->nullable();
            $table->boolean('completed')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->boolean('repeat')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('Responsible_id')->nullable()->constrained('users')->onDelete('cascade'); // مسئول
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete(); // مسئول
            $table->foreignId('minutes_id')->nullable()->constrained('minutes')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
