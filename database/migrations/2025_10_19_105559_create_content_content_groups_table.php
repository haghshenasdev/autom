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
        Schema::create('content_content_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_group_id')->constrained('content_groups')->cascadeOnDelete();
            $table->foreignId('content_id')->nullable()->constrained('contents')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_groups');
    }
};
