<?php

namespace Domain\Identidade\Entities;

use Domain\Shared\ValueObjects\Placa;

class Veiculo
{
    public function __construct(
        private readonly ?int $id,
        private Placa $placa,
        private string $marca,
        private string $modelo,
        private int $ano,
        private int $clienteId,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getPlaca(): Placa { return $this->placa; }
    public function getMarca(): string { return $this->marca; }
    public function getModelo(): string { return $this->modelo; }
    public function getAno(): int { return $this->ano; }
    public function getClienteId(): int { return $this->clienteId; }

    public function atualizar(string $marca, string $modelo, int $ano): void
    {
        $this->marca = $marca;
        $this->modelo = $modelo;
        $this->ano = $ano;
    }
}
