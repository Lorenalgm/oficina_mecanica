<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Repositories\OSRepository;
use RuntimeException;

class CriarOS
{
    public function __construct(private OSRepository $repositorio) {}

    /**
     * Abre uma OS com cliente, veículo e problema. Serviços e peças (insumos) são
     * opcionais na abertura — cada item de $servicos aceita:
     *   ['servico_id' => int, 'insumos' => [['insumo_id' => int, 'quantidade' => int], ...]]
     *
     * @param array<int, array{servico_id: int, insumos?: array<int, array{insumo_id: int, quantidade?: int}>}> $servicos
     */
    public function executar(int $veiculoId, int $clienteId, string $descricaoProblema, array $servicos = []): OS
    {
        $statusId = $this->repositorio->findStatusIdByNome('Recebida');

        if ($statusId === null) {
            throw new RuntimeException('Status "Recebida" não configurado.');
        }

        // Valida serviços e insumos antes de persistir, para não criar uma OS parcial.
        foreach ($servicos as $servico) {
            $servicoId = (int) ($servico['servico_id'] ?? 0);

            if (!$this->repositorio->servicoExiste($servicoId)) {
                throw new RuntimeException("Serviço #{$servicoId} não encontrado.");
            }

            foreach ($servico['insumos'] ?? [] as $insumo) {
                $insumoId = (int) ($insumo['insumo_id'] ?? 0);

                if (!$this->repositorio->insumoExiste($insumoId)) {
                    throw new RuntimeException("Insumo #{$insumoId} não encontrado.");
                }
            }
        }

        $os = new OS(
            id: null,
            clienteId: $clienteId,
            veiculoId: $veiculoId,
            statusAtualId: $statusId,
            descricaoProblema: $descricaoProblema,
        );

        $os = $this->repositorio->save($os);

        $this->repositorio->registrarStatus($os->getId(), $statusId);

        foreach ($servicos as $servico) {
            $osServico = $this->repositorio->adicionarServico($os->getId(), (int) $servico['servico_id']);

            foreach ($servico['insumos'] ?? [] as $insumo) {
                $this->repositorio->adicionarInsumo(
                    $osServico->getId(),
                    (int) $insumo['insumo_id'],
                    (int) ($insumo['quantidade'] ?? 1),
                );
            }
        }

        return $os;
    }
}
