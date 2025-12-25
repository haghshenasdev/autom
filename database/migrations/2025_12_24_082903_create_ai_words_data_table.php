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
        Schema::create('ai_words_data', function (Blueprint $table) {
            $table->id();
            $table->json('allowed_words');   // لیست کلمات مجاز
            $table->json('blocked_words')->nullable();   // لیست کلمات غیرمجاز
            $table->float('sensitivity')->nullable();  // میزان حساسیت عددی
            $table->string('model_type');                // نوع مدل (morph)
            $table->unsignedBigInteger('model_id');      // آیدی مدل مربوطه
            $table->string('target_field')->nullable();  // نام فیلد در مدل مقصد
            $table->timestamps();

            // ایندکس برای morph relation
            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_words_data');
    }
};
