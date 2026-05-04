<?php

namespace Domain\Atendimento\Entities;

class OSServico
{
    public function __construct(
        private readonly ?int $id,
        private int $osId,
        private int $servicoId,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getOsId(): int { return $this->osId; }
    public function getServicoId(): int { return $this->servicoId; }
}
