<?php

namespace Domain\Atendimento\Saga;

enum Compensacao: string
{
    case EstornarPagamento = 'estornar_pagamento';
    case DevolverEstoque = 'devolver_estoque';
    case CancelarOrcamento = 'cancelar_orcamento';
    case CancelarOS = 'cancelar_os';

    public function remota(): bool
    {
        return in_array($this, [self::EstornarPagamento, self::CancelarOrcamento], true);
    }
}
