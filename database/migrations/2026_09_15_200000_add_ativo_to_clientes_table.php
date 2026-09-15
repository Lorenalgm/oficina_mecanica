<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status do cliente consultado pela Lambda de autenticação: cliente inativo
     * não recebe token. Clientes existentes nascem ativos.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('ativo');
        });
    }
};
