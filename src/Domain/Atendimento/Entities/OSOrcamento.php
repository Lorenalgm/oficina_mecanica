<?php

namespace Domain\Atendimento\Entities;

use DateTimeInterface;
use Domain\Atendimento\Enums\StatusOrcamento;

class OSOrcamento
{
    public function __construct(
        private readonly ?int $id,
        private int $osId,
        private float $valorTotal,
        private DateTimeInterface $dataOrcamento,
        private ?DateTimeInterface $dataAprovacao,
        private StatusOrcamento $status,
        private ?string $approvalToken = null,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getOsId(): int { return $this->osId; }
    public function getValorTotal(): float { return $this->valorTotal; }
    public function getDataOrcamento(): DateTimeInterface { return $this->dataOrcamento; }
    public function getDataAprovacao(): ?DateTimeInterface { return $this->dataAprovacao; }
    public function getStatus(): StatusOrcamento { return $this->status; }
    public function getApprovalToken(): ?string { return $this->approvalToken; }

    public function aprovar(DateTimeInterface $dataAprovacao): void
    {
        $this->status = StatusOrcamento::Aprovado;
        $this->dataAprovacao = $dataAprovacao;
        $this->invalidarToken();
    }

    public function recusar(): void
    {
        $this->status = StatusOrcamento::Recusado;
        $this->invalidarToken();
    }

    public function isPendente(): bool
    {
        return $this->status === StatusOrcamento::Pendente;
    }

    /**
     * Invalida o token de aprovação (uso único), evitando replay após aprovar/recusar.
     */
    public function invalidarToken(): void
    {
        $this->approvalToken = null;
    }
}
