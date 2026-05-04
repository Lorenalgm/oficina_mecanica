<?php

namespace Tests\Unit\Domain\Shared\ValueObjects;

use Domain\Shared\ValueObjects\Placa;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PlacaTest extends TestCase
{
    public function test_formato_antigo_valido(): void
    {
        $placa = new Placa('ABC-1234');
        $this->assertEquals('ABC-1234', $placa->getValue());
    }

    public function test_formato_antigo_minusculas_aceito(): void
    {
        $placa = new Placa('abc-1234');
        $this->assertEquals('ABC-1234', $placa->getValue());
    }

    public function test_mercosul_valido(): void
    {
        $placa = new Placa('ABC1D23');
        $this->assertEquals('ABC1D23', $placa->getValue());
    }

    public function test_mercosul_minusculas_aceito(): void
    {
        $placa = new Placa('abc1d23');
        $this->assertEquals('ABC1D23', $placa->getValue());
    }

    public function test_numeros_no_inicio_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Placa('1234ABC');
    }

    public function test_formato_vazio_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Placa('');
    }

    public function test_caracteres_especiais_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Placa('ABC-12@4');
    }

    public function test_letras_demais_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Placa('ABCD-123');
    }

    public function test_to_string_retorna_valor(): void
    {
        $placa = new Placa('ABC-1234');
        $this->assertEquals('ABC-1234', (string) $placa);
    }
}
