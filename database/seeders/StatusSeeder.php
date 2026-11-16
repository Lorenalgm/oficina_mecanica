<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $status = [
            'Recebida',
            'Em diagnóstico',
            'Aguardando aprovação',
            'Aguardando pagamento',
            'Em execução',
            'Finalizada',
            'Entregue',
            'Cancelada',
        ];

        foreach ($status as $nome) {
            DB::table('status')->updateOrInsert(['nome' => $nome], ['nome' => $nome]);
        }
    }
}
