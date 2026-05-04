<?php

namespace Domain\Catalogo\Exceptions;

use RuntimeException;

class EstoqueInsuficienteException extends RuntimeException
{
    public function __construct(string $nomeInsumo, int $disponivel, int $solicitado)
    {
        parent::__construct(
            "Estoque insuficiente para '{$nomeInsumo}': disponível {$disponivel}, solicitado {$solicitado}."
        );
    }
}
