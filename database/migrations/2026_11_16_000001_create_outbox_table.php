<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuid('saga_id');
            $table->text('corpo');
            $table->timestamp('criado_em');
            $table->timestamp('publicado_em')->nullable();

            $table->index(['publicado_em', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
