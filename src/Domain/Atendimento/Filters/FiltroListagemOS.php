<?php

namespace Domain\Atendimento\Filters;

/**
 * Filtros opcionais para a listagem de OS (GET /api/os).
 */
class FiltroListagemOS
{
    public function __construct(
        public readonly ?int $clienteId = null,
        public readonly ?int $veiculoId = null,
        public readonly ?int $statusId = null,
    ) {}

    /**
     * @param array<string, mixed> $filtros
     */
    public static function fromArray(array $filtros): self
    {
        return new self(
            clienteId: isset($filtros['cliente_id']) ? (int) $filtros['cliente_id'] : null,
            veiculoId: isset($filtros['veiculo_id']) ? (int) $filtros['veiculo_id'] : null,
            statusId: isset($filtros['status_id']) ? (int) $filtros['status_id'] : null,
        );
    }
}
