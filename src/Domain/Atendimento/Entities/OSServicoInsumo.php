<?php

namespace Domain\Atendimento\Entities;

class OSServicoInsumo
{
    private ?string $insumoNome = null;
    private ?float $insumoValor = null;

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
    public function getInsumoNome(): ?string { return $this->insumoNome; }
    public function getInsumoValor(): ?float { return $this->insumoValor; }

    public function setInsumoNome(?string $nome): void { $this->insumoNome = $nome; }
    public function setInsumoValor(?float $valor): void { $this->insumoValor = $valor; }
}
