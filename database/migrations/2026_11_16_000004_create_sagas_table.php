<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sagas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('os_id')->constrained('os')->cascadeOnDelete();
            $table->string('correlation_id');
            $table->string('estado', 40);
            $table->unsignedBigInteger('orcamento_id')->nullable();
            $table->unsignedBigInteger('valor_total')->nullable();
            $table->string('link_aprovacao')->nullable();
            $table->unsignedBigInteger('pagamento_id')->nullable();
            $table->string('link_pagamento')->nullable();
            $table->string('execucao_id')->nullable();
            $table->boolean('estoque_baixado')->default(false);
            $table->json('compensacoes_pendentes');
            $table->unsignedSmallInteger('tentativas_compensacao')->default(0);
            $table->string('motivo_falha')->nullable();
            $table->uuid('ultimo_comando_id')->nullable();
            $table->timestamps();

            $table->index(['os_id', 'estado']);
        });

        Schema::create('saga_passos', function (Blueprint $table) {
            $table->id();
            $table->uuid('saga_id');
            $table->foreign('saga_id')->references('id')->on('sagas')->cascadeOnDelete();
            $table->string('passo');
            $table->json('detalhes');
            $table->timestamp('registrado_em');

            $table->index(['saga_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saga_passos');
        Schema::dropIfExists('sagas');
    }
};
