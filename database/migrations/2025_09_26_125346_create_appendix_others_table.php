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
        Schema::create('appendix_others', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('appendix_other_id');
            $table->string('appendix_other_type',100);
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->string('file');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appendix_others');
    }
};
