<?php

namespace Domain\Atendimento\Exceptions;

use RuntimeException;

/**
 * Lançada quando a aprovação/recusa de um orçamento é tentada sem um
 * token de aprovação válido (ausente, incorreto ou já utilizado).
 */
class TokenAprovacaoInvalido extends RuntimeException
{
    public function __construct(string $message = 'Token de aprovação inválido ou ausente.')
    {
        parent::__construct($message);
    }
}
