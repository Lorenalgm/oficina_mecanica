<?php

namespace Domain\Atendimento\Entities;

class OSServicoInsumo
{
    public function __construct(
        private readonly ?int $id,
        private int $osServicoId,
        private int $insumoId,
        private int $quantidade,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getOsServicoId(): int { return $this->osServicoId; }
    public function getInsumoId(): int { return $this->insumoId; }
    public function getQuantidade(): int { return $this->quantidade; }
}
