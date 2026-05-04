<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('status_atual_id')->constrained('status');
            $table->text('descricao_problema');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os');
    }
};
