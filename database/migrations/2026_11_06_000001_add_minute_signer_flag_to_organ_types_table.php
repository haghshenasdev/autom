<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organ_types', function (Blueprint $table) {
            $table->boolean('is_minute_signer_enabled')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('organ_types', function (Blueprint $table) {
            $table->dropColumn('is_minute_signer_enabled');
        });
    }
};
