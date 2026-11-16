<?php

namespace Domain\Atendimento\Enums;

/**
 * Prioridade de exibição dos status na listagem de OS.
 *
 * Peso menor = maior prioridade (aparece antes na listagem):
 * Em execução (1) > Aguardando pagamento (2) > Aguardando aprovação (3) > Em diagnóstico (4) > Recebida (5).
 * Os status "Finalizada", "Entregue" e "Cancelada" não têm prioridade — são excluídos da listagem.
 */
enum PrioridadeStatus: string
{
    case EmExecucao = 'Em execução';
    case AguardandoPagamento = 'Aguardando pagamento';
    case AguardandoAprovacao = 'Aguardando aprovação';
    case EmDiagnostico = 'Em diagnóstico';
    case Recebida = 'Recebida';

    /** Status que não aparecem na listagem de OS (exclusão lógica). */
    public const EXCLUIDOS_DA_LISTAGEM = ['Finalizada', 'Entregue', 'Cancelada'];

    public function peso(): int
    {
        return match ($this) {
            self::EmExecucao => 1,
            self::AguardandoPagamento => 2,
            self::AguardandoAprovacao => 3,
            self::EmDiagnostico => 4,
            self::Recebida => 5,
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
