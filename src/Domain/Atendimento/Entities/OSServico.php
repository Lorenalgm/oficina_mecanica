<?php

namespace Domain\Atendimento\Entities;

class OSServico
{
    private ?string $servicoNome = null;
    private ?float $servicoValor = null;
    private ?array $insumos = null;

    public function __construct(
        private readonly ?int $id,
        private int $osId,
        private int $servicoId,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getOsId(): int { return $this->osId; }
    public function getServicoId(): int { return $this->servicoId; }
    public function getServicoNome(): ?string { return $this->servicoNome; }
    public function getServicoValor(): ?float { return $this->servicoValor; }
    public function getInsumos(): ?array { return $this->insumos; }

    public function setServicoNome(?string $nome): void { $this->servicoNome = $nome; }
    public function setServicoValor(?float $valor): void { $this->servicoValor = $valor; }
    public function setInsumos(?array $insumos): void { $this->insumos = $insumos; }
}
