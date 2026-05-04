<?php

namespace Tests\Unit\Domain\Shared\ValueObjects;

use Domain\Shared\ValueObjects\Documento;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentoTest extends TestCase
{
    public function test_cpf_valido_com_mascara(): void
    {
        $doc = new Documento('529.982.247-25');
        $this->assertEquals('52998224725', $doc->getValue());
    }

    public function test_cpf_valido_sem_mascara(): void
    {
        $doc = new Documento('52998224725');
        $this->assertEquals('52998224725', $doc->getValue());
    }

    public function test_cpf_invalido_digitos_verificadores(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento('529.982.247-26');
    }

    public function test_cpf_invalido_todos_iguais(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento('111.111.111-11');
    }

    public function test_cpf_invalido_zeros(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento('000.000.000-00');
    }

    public function test_cnpj_valido_com_mascara(): void
    {
        $doc = new Documento('11.222.333/0001-81');
        $this->assertEquals('11222333000181', $doc->getValue());
    }

    public function test_cnpj_valido_sem_mascara(): void
    {
        $doc = new Documento('11222333000181');
        $this->assertEquals('11222333000181', $doc->getValue());
    }

    public function test_cnpj_invalido_digitos_verificadores(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento('11.222.333/0001-82');
    }

    public function test_cnpj_invalido_todos_iguais(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento('11.111.111/1111-11');
    }

    public function test_formato_completamente_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento('abc');
    }

    public function test_string_vazia(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento('');
    }

    public function test_to_string_retorna_valor(): void
    {
        $doc = new Documento('52998224725');
        $this->assertEquals('52998224725', (string) $doc);
    }
}
