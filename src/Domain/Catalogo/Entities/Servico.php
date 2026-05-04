<?php

namespace Domain\Catalogo\Entities;

class Servico
{
    public function __construct(
        private readonly ?int $id,
        private string $nome,
        private float $valor,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getValor(): float { return $this->valor; }

    public function atualizar(string $nome, float $valor): void
    {
        $this->nome = $nome;
        $this->valor = $valor;
    }
}
