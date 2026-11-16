<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens_processadas', function (Blueprint $table) {
            $table->uuid('message_id')->primary();
            $table->string('type');
            $table->uuid('saga_id');
            $table->timestamp('processado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens_processadas');
    }
};
