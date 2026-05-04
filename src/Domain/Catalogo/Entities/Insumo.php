<?php

namespace Domain\Catalogo\Entities;

use Domain\Catalogo\Exceptions\EstoqueInsuficienteException;
use InvalidArgumentException;

class Insumo
{
    public function __construct(
        private readonly ?int $id,
        private string $nome,
        private float $valor,
        private int $quantidadeEstoque,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getValor(): float { return $this->valor; }
    public function getQuantidadeEstoque(): int { return $this->quantidadeEstoque; }

    public function darBaixa(int $quantidade): void
    {
        if ($quantidade < 0) {
            throw new InvalidArgumentException('A quantidade para baixa não pode ser negativa.');
        }

        if ($this->quantidadeEstoque - $quantidade < 0) {
            throw new EstoqueInsuficienteException($this->nome, $this->quantidadeEstoque, $quantidade);
        }

        $this->quantidadeEstoque -= $quantidade;
    }

    public function atualizar(string $nome, float $valor, int $quantidadeEstoque): void
    {
        $this->nome = $nome;
        $this->valor = $valor;
        $this->quantidadeEstoque = $quantidadeEstoque;
    }
}
