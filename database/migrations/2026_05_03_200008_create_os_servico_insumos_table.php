<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os_servico_insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('os_servico_id')->constrained('os_servicos')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos');
            $table->integer('quantidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_servico_insumos');
    }
};
