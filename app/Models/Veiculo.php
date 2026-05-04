<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veiculo extends Model
{
    use HasFactory;

    protected $fillable = ['placa', 'marca', 'modelo', 'ano', 'cliente_id'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OS::class);
    }
}
