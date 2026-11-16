<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Saga extends Model
{
    use HasUuids;

    protected $table = 'sagas';

    protected $fillable = [
        'id', 'os_id', 'correlation_id', 'estado', 'orcamento_id', 'valor_total', 'link_aprovacao',
        'pagamento_id', 'link_pagamento', 'execucao_id', 'estoque_baixado', 'compensacoes_pendentes',
        'tentativas_compensacao', 'motivo_falha', 'ultimo_comando_id',
    ];

    protected $casts = [
        'os_id' => 'integer',
        'orcamento_id' => 'integer',
        'valor_total' => 'integer',
        'pagamento_id' => 'integer',
        'estoque_baixado' => 'boolean',
        'compensacoes_pendentes' => 'array',
        'tentativas_compensacao' => 'integer',
    ];
}
