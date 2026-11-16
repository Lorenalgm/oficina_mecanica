<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('os', function (Blueprint $table) {
            $table->string('filial', 40)->default('MATRIZ')->after('status_atual_id');
        });
    }

    public function down(): void
    {
        Schema::table('os', function (Blueprint $table) {
            $table->dropColumn('filial');
        });
    }
};
