<?php

namespace Domain\Atendimento\Entities;

use DateTimeInterface;

class OSStatus
{
    private ?string $statusNome = null;

    public function __construct(
        private readonly ?int $id,
        private int $osId,
        private int $statusId,
        private DateTimeInterface $dataStatus,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getOsId(): int { return $this->osId; }
    public function getStatusId(): int { return $this->statusId; }
    public function getDataStatus(): DateTimeInterface { return $this->dataStatus; }
    public function getStatusNome(): ?string { return $this->statusNome; }

    public function setStatusNome(?string $nome): void { $this->statusNome = $nome; }
}
