<?php

namespace Tests\Unit\Domain\Catalogo\Entities;

use Domain\Catalogo\Entities\Insumo;
use Domain\Catalogo\Exceptions\EstoqueInsuficienteException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InsumoTest extends TestCase
{
    private function criarInsumo(int $estoque = 10): Insumo
    {
        return new Insumo(id: 1, nome: 'Filtro de óleo', valor: 25.0, quantidadeEstoque: $estoque);
    }

    public function test_dar_baixa_com_estoque_suficiente(): void
    {
        $insumo = $this->criarInsumo(10);
        $insumo->darBaixa(3);
        $this->assertEquals(7, $insumo->getQuantidadeEstoque());
    }

    public function test_dar_baixa_com_estoque_exato_zero(): void
    {
        $insumo = $this->criarInsumo(5);
        $insumo->darBaixa(5);
        $this->assertEquals(0, $insumo->getQuantidadeEstoque());
    }

    public function test_dar_baixa_com_estoque_insuficiente_lanca_excecao(): void
    {
        $this->expectException(EstoqueInsuficienteException::class);
        $insumo = $this->criarInsumo(2);
        $insumo->darBaixa(3);
    }

    public function test_estoque_nao_alterado_apos_excecao(): void
    {
        $insumo = $this->criarInsumo(2);
        try {
            $insumo->darBaixa(3);
        } catch (EstoqueInsuficienteException) {}
        $this->assertEquals(2, $insumo->getQuantidadeEstoque());
    }

    public function test_dar_baixa_com_quantidade_negativa_lanca_excecao(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $insumo = $this->criarInsumo(10);
        $insumo->darBaixa(-1);
    }
}
