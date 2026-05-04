<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OSServicoInsumo extends Model
{
    public $timestamps = false;

    protected $table = 'os_servico_insumos';

    protected $fillable = ['os_servico_id', 'insumo_id', 'quantidade'];

    public function osServico(): BelongsTo
    {
        return $this->belongsTo(OSServico::class, 'os_servico_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
