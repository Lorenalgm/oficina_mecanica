<?php

namespace Domain\Shared\ValueObjects;

use InvalidArgumentException;

class Placa
{
    private string $valor;

    public function __construct(string $valor)
    {
        $normalizado = strtoupper(trim($valor));

        if (!$this->validarPlaca($normalizado)) {
            throw new InvalidArgumentException("Placa inválida: {$valor}");
        }

        $this->valor = $normalizado;
    }

    public function getValue(): string
    {
        return $this->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }

    private function validarPlaca(string $placa): bool
    {
        // Formato antigo: AAA-0000
        if (preg_match('/^[A-Z]{3}-\d{4}$/', $placa)) {
            return true;
        }

        // Formato Mercosul: AAA0A00
        if (preg_match('/^[A-Z]{3}\d[A-Z]\d{2}$/', $placa)) {
            return true;
        }

        return false;
    }
}
