<?php

namespace Application\Saga\UseCases;

use Application\Saga\ComandosDaSaga;
use Application\Saga\SincronizadorDeStatus;
use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Repositories\OSRepository;
use Domain\Atendimento\Saga\SagaJaAtiva;
use Domain\Atendimento\Saga\SagaOS;
use Domain\Atendimento\Saga\SagaRepository;
use Domain\Identidade\Repositories\ClienteRepository;
use Domain\Identidade\Repositories\VeiculoRepository;
use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Transacao;
use RuntimeException;

class IniciarSagaOS
{
    public function __construct(
        private OSRepository $ordens,
        private ClienteRepository $clientes,
        private VeiculoRepository $veiculos,
        private SagaRepository $sagas,
        private ComandosDaSaga $comandos,
        private SincronizadorDeStatus $status,
        private Transacao $transacao,
    ) {}

    public function executar(int $osId, string $correlationId): SagaOS
    {
        return $this->transacao->executar(function () use ($osId, $correlationId) {
            $os = $this->ordens->findById($osId)
                ?? throw new RuntimeException("OS #{$osId} não encontrada.");

            if ($ativa = $this->sagas->buscarAtivaDaOS($osId)) {
                throw new SagaJaAtiva($ativa);
            }
            if (empty($os->getServicos())) {
                throw new RuntimeException('A OS não possui serviços para gerar orçamento.');
            }

            $saga = SagaOS::iniciar(Envelope::novoUuid(), $osId, $correlationId);
            $this->sagas->salvar($saga);
            $this->comandos->enviar($saga, 'billing.cmd.gerar-orcamento', $this->pedidoDeOrcamento($os));
            $this->sagas->salvar($saga);
            $this->status->aplicar($saga);

            return $saga;
        });
    }

    private function pedidoDeOrcamento(OS $os): array
    {
        $cliente = $this->clientes->findById($os->getClienteId())
            ?? throw new RuntimeException("Cliente #{$os->getClienteId()} não encontrado.");
        $veiculo = $this->veiculos->findById($os->getVeiculoId())
            ?? throw new RuntimeException("Veículo #{$os->getVeiculoId()} não encontrado.");

        $itens = [];
        /** @var OSServico $servico */
        foreach ($os->getServicos() as $servico) {
            $itens[] = [
                'tipo' => 'servico',
                'referencia_id' => $servico->getServicoId(),
                'descricao' => $servico->getServicoNome(),
                'quantidade' => 1,
                'valor_unitario' => self::centavos($servico->getServicoValor()),
            ];
            /** @var OSServicoInsumo $insumo */
            foreach ($servico->getInsumos() ?? [] as $insumo) {
                $itens[] = [
                    'tipo' => 'insumo',
                    'referencia_id' => $insumo->getInsumoId(),
                    'descricao' => $insumo->getInsumoNome(),
                    'quantidade' => $insumo->getQuantidade(),
                    'valor_unitario' => self::centavos($insumo->getInsumoValor()),
                ];
            }
        }

        return [
            'cliente' => [
                'id' => $cliente->getId(),
                'nome' => $cliente->getNome(),
                'email' => $cliente->getEmail(),
                'documento' => $cliente->getDocumento()->getValue(),
            ],
            'veiculo' => [
                'placa' => $veiculo->getPlaca()->getValue(),
                'modelo' => "{$veiculo->getMarca()} {$veiculo->getModelo()}",
            ],
            'itens' => $itens,
            'valor_total' => array_sum(array_map(fn (array $i) => $i['valor_unitario'] * $i['quantidade'], $itens)),
        ];
    }

    private static function centavos(?float $valor): int
    {
        return (int) round(($valor ?? 0) * 100);
    }
}
