<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('os_id')->constrained('os')->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('status');
            $table->timestamp('data_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_status');
    }
};
