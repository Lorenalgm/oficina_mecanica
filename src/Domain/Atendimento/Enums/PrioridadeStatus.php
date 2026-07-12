<?php

namespace Domain\Atendimento\Enums;

/**
 * Prioridade de exibição dos status na listagem de OS.
 *
 * Peso menor = maior prioridade (aparece antes na listagem):
 * Em execução (1) > Aguardando aprovação (2) > Em diagnóstico (3) > Recebida (4).
 * Os status "Finalizada" e "Entregue" não têm prioridade — são excluídos da listagem.
 */
enum PrioridadeStatus: string
{
    case EmExecucao = 'Em execução';
    case AguardandoAprovacao = 'Aguardando aprovação';
    case EmDiagnostico = 'Em diagnóstico';
    case Recebida = 'Recebida';

    /** Status que não aparecem na listagem de OS (exclusão lógica). */
    public const EXCLUIDOS_DA_LISTAGEM = ['Finalizada', 'Entregue'];

    public function peso(): int
    {
        return match ($this) {
            self::EmExecucao => 1,
            self::AguardandoAprovacao => 2,
            self::EmDiagnostico => 3,
            self::Recebida => 4,
        };
    }

    /**
     * Mapa nome do status => peso, na ordem de prioridade.
     *
     * @return array<string, int>
     */
    public static function mapaDePeso(): array
    {
        $mapa = [];
        foreach (self::cases() as $case) {
            $mapa[$case->value] = $case->peso();
        }

        return $mapa;
    }
}
