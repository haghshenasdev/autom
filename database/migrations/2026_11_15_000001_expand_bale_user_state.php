<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bale_users', function (Blueprint $table) {
            $table->text('state')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bale_users', function (Blueprint $table) {
            $table->string('state')->nullable()->change();
        });
    }
};
