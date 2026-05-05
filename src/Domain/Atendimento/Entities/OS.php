<?php

namespace Domain\Atendimento\Entities;

use DateTimeInterface;

class OS
{
    private ?string $statusAtualNome = null;
    private ?string $clienteNome = null;
    private ?string $veiculoPlaca = null;
    private ?DateTimeInterface $createdAt = null;
    private ?OSOrcamento $orcamento = null;
    private ?array $servicos = null;
    private ?array $historicoStatus = null;

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
    public function getStatusAtualNome(): ?string { return $this->statusAtualNome; }
    public function getClienteNome(): ?string { return $this->clienteNome; }
    public function getVeiculoPlaca(): ?string { return $this->veiculoPlaca; }
    public function getCreatedAt(): ?DateTimeInterface { return $this->createdAt; }
    public function getOrcamento(): ?OSOrcamento { return $this->orcamento; }
    public function getServicos(): ?array { return $this->servicos; }
    public function getHistoricoStatus(): ?array { return $this->historicoStatus; }

    public function setStatusAtualNome(?string $nome): void { $this->statusAtualNome = $nome; }
    public function setClienteNome(?string $nome): void { $this->clienteNome = $nome; }
    public function setVeiculoPlaca(?string $placa): void { $this->veiculoPlaca = $placa; }
    public function setCreatedAt(?DateTimeInterface $dt): void { $this->createdAt = $dt; }
    public function setOrcamento(?OSOrcamento $orcamento): void { $this->orcamento = $orcamento; }
    public function setServicos(?array $servicos): void { $this->servicos = $servicos; }
    public function setHistoricoStatus(?array $historico): void { $this->historicoStatus = $historico; }

    public function alterarStatus(int $novoStatusId): void
    {
        $this->statusAtualId = $novoStatusId;
    }
}
