<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'documento', 'celular', 'email'];

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class);
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OS::class);
    }
}
