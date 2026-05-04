<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os_orcamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('os_id')->constrained('os')->cascadeOnDelete();
            $table->decimal('valor_total', 10, 2);
            $table->timestamp('data_orcamento');
            $table->timestamp('data_aprovacao')->nullable();
            $table->string('status')->default('pendente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_orcamentos');
    }
};
