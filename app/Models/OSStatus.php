<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OSStatus extends Model
{
    public $timestamps = false;

    protected $table = 'os_status';

    protected $fillable = ['os_id', 'status_id', 'data_status'];

    protected $casts = ['data_status' => 'datetime'];

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
