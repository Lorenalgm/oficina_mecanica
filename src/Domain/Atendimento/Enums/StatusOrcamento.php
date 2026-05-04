<?php

namespace Domain\Atendimento\Enums;

enum StatusOrcamento: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Recusado = 'recusado';
}
