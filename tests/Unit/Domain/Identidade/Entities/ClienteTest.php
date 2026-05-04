<?php

namespace Tests\Unit\Domain\Identidade\Entities;

use Domain\Identidade\Entities\Cliente;
use Domain\Shared\ValueObjects\Documento;
use PHPUnit\Framework\TestCase;

class ClienteTest extends TestCase
{
    public function test_cliente_criado_com_documento_valido(): void
    {
        $cliente = new Cliente(
            id: null,
            nome: 'João Silva',
            documento: new Documento('52998224725'),
            celular: null,
            email: null,
        );

        $this->assertNull($cliente->getId());
        $this->assertEquals('João Silva', $cliente->getNome());
        $this->assertEquals('52998224725', $cliente->getDocumento()->getValue());
    }

    public function test_atualizar_cliente(): void
    {
        $cliente = new Cliente(
            id: 1,
            nome: 'Nome Antigo',
            documento: new Documento('52998224725'),
            celular: null,
            email: null,
        );

        $cliente->atualizar('Nome Novo', '(11) 99999-9999', 'novo@email.com');

        $this->assertEquals('Nome Novo', $cliente->getNome());
        $this->assertEquals('(11) 99999-9999', $cliente->getCelular());
        $this->assertEquals('novo@email.com', $cliente->getEmail());
    }
}
