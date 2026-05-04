<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OSServico extends Model
{
    public $timestamps = false;

    protected $table = 'os_servicos';

    protected $fillable = ['os_id', 'servico_id'];

    public function os(): BelongsTo
    {
        return $this->belongsTo(OS::class, 'os_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    public function insumos(): HasMany
    {
        return $this->hasMany(OSServicoInsumo::class, 'os_servico_id');
    }
}
