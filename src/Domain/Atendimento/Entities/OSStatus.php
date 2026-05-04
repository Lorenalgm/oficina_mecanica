<?php

namespace Domain\Atendimento\Entities;

use DateTimeInterface;

class OSStatus
{
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
}
