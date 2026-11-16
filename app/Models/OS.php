<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OS extends Model
{
    use HasFactory;

    protected $table = 'os';

    protected $fillable = ['veiculo_id', 'cliente_id', 'status_atual_id', 'descricao_problema', 'filial'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function statusAtual(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_atual_id');
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(OSServico::class, 'os_id');
    }

    public function historicoStatus(): HasMany
    {
        return $this->hasMany(OSStatus::class, 'os_id');
    }

}
