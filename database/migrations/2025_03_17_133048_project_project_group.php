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
        Schema::create('project_project_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_group_id')->constrained('project_groups')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_project_group');
    }
};
