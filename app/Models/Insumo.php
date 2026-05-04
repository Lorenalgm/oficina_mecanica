<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['nome', 'valor', 'quantidade_estoque'];
}
