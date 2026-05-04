<?php

namespace Domain\Atendimento\Entities;

class OS
{
    public function __construct(
        private readonly ?int $id,
        private int $clienteId,
        private int $veiculoId,
        private int $statusAtualId,
        private string $descricaoProblema,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getClienteId(): int { return $this->clienteId; }
    public function getVeiculoId(): int { return $this->veiculoId; }
    public function getStatusAtualId(): int { return $this->statusAtualId; }
    public function getDescricaoProblema(): string { return $this->descricaoProblema; }

    public function alterarStatus(int $novoStatusId): void
    {
        $this->statusAtualId = $novoStatusId;
    }
}
