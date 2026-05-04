<?php

namespace Domain\Shared\ValueObjects;

use InvalidArgumentException;

class Documento
{
    private string $valor;

    public function __construct(string $valor)
    {
        $normalizado = preg_replace('/\D/', '', $valor);

        if (strlen($normalizado) === 11) {
            if (!$this->validarCpf($normalizado)) {
                throw new InvalidArgumentException("CPF inválido: {$valor}");
            }
        } elseif (strlen($normalizado) === 14) {
            if (!$this->validarCnpj($normalizado)) {
                throw new InvalidArgumentException("CNPJ inválido: {$valor}");
            }
        } else {
            throw new InvalidArgumentException("Documento inválido: {$valor}");
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

    private function validarCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += (int) $cpf[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;

        if ((int) $cpf[9] !== $digito1) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += (int) $cpf[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;

        return (int) $cpf[10] === $digito2;
    }

    private function validarCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += (int) $cnpj[$i] * $pesos1[$i];
        }
        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;

        if ((int) $cnpj[12] !== $digito1) {
            return false;
        }

        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += (int) $cnpj[$i] * $pesos2[$i];
        }
        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;

        return (int) $cnpj[13] === $digito2;
    }
}
