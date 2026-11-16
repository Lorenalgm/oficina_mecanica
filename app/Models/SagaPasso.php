<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SagaPasso extends Model
{
    public $timestamps = false;

    protected $table = 'saga_passos';

    protected $fillable = ['saga_id', 'passo', 'detalhes', 'registrado_em'];

    protected $casts = [
        'detalhes' => 'array',
        'registrado_em' => 'datetime',
    ];
}
