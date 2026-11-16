<?php

namespace Infrastructure\Persistence;

use Domain\Shared\Transacao;
use Illuminate\Support\Facades\DB;

class LaravelTransacao implements Transacao
{
    public function executar(callable $operacao): mixed
    {
        return DB::transaction(fn () => $operacao());
    }
}
