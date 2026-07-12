<?php

namespace Tests\Unit\Application\OS;

use Application\OS\UseCases\CalcularTempoMedio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalcularTempoMedioSemStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_retorna_null_quando_status_nao_existem(): void
    {
        // Banco limpo sem seeder — nenhum registro em "status"
        $resultado = app(CalcularTempoMedio::class)->executar();

        $this->assertNull($resultado);
    }
}
