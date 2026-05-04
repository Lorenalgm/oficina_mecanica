<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OSOrcamento extends Model
{
    public $timestamps = false;

    protected $table = 'os_orcamentos';

    protected $fillable = ['os_id', 'valor_total', 'data_orcamento', 'data_aprovacao', 'status'];

    protected $casts = [
        'data_orcamento' => 'datetime',
        'data_aprovacao' => 'datetime',
    ];

    public function os(): BelongsTo
    {
        return $this->belongsTo(OS::class, 'os_id');
    }
}
