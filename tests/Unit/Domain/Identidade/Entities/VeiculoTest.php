<?php

namespace Tests\Unit\Domain\Identidade\Entities;

use Domain\Identidade\Entities\Veiculo;
use Domain\Shared\ValueObjects\Placa;
use PHPUnit\Framework\TestCase;

class VeiculoTest extends TestCase
{
    public function test_veiculo_criado_com_placa_valida(): void
    {
        $veiculo = new Veiculo(
            id: null,
            placa: new Placa('ABC-1234'),
            marca: 'Toyota',
            modelo: 'Corolla',
            ano: 2020,
            clienteId: 1,
        );

        $this->assertNull($veiculo->getId());
        $this->assertEquals('ABC-1234', $veiculo->getPlaca()->getValue());
        $this->assertEquals(1, $veiculo->getClienteId());
    }

    public function test_veiculo_com_placa_mercosul(): void
    {
        $veiculo = new Veiculo(
            id: 1,
            placa: new Placa('ABC1D23'),
            marca: 'Honda',
            modelo: 'Civic',
            ano: 2022,
            clienteId: 2,
        );

        $this->assertEquals('ABC1D23', $veiculo->getPlaca()->getValue());
    }

    public function test_atualizar_veiculo(): void
    {
        $veiculo = new Veiculo(
            id: 1,
            placa: new Placa('ABC-1234'),
            marca: 'Toyota',
            modelo: 'Corolla',
            ano: 2020,
            clienteId: 1,
        );

        $veiculo->atualizar('Ford', 'Fiesta', 2021);

        $this->assertEquals('Ford', $veiculo->getMarca());
        $this->assertEquals('Fiesta', $veiculo->getModelo());
        $this->assertEquals(2021, $veiculo->getAno());
    }
}
