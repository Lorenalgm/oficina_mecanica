<?php

namespace Domain\Atendimento\Saga;

enum EstadoSaga: string
{
    case AguardandoOrcamento = 'aguardando_orcamento';
    case AguardandoAprovacao = 'aguardando_aprovacao';
    case ReservandoEstoque = 'reservando_estoque';
    case AguardandoPagamento = 'aguardando_pagamento';
    case AguardandoExecucao = 'aguardando_execucao';
    case Concluida = 'concluida';
    case Compensando = 'compensando';
    case Compensada = 'compensada';
    case CompensacaoFalhou = 'compensacao_falhou';

    public function statusDaOS(): ?string
    {
        return match ($this) {
            self::AguardandoOrcamento => 'Em diagnóstico',
            self::AguardandoAprovacao => 'Aguardando aprovação',
            self::ReservandoEstoque, self::AguardandoPagamento => 'Aguardando pagamento',
            self::AguardandoExecucao, self::Concluida => 'Em execução',
            self::Compensada => 'Cancelada',
            self::Compensando, self::CompensacaoFalhou => null,
        };
    }

    public function ativa(): bool
    {
        return ! in_array($this, [self::Concluida, self::Compensada, self::CompensacaoFalhou], true);
    }
}
