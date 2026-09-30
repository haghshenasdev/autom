<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('minute_project')) {
            return;
        }

        Schema::create('minute_project', function (Blueprint $table) {
            $table->foreignId('minute_id')->constrained('minutes')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->primary(['minute_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_project');
    }
};
