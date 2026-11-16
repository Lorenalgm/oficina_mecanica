<?php

namespace Tests\Unit\Domain\Atendimento\Enums;

use Domain\Atendimento\Enums\PrioridadeStatus;
use PHPUnit\Framework\TestCase;

class PrioridadeStatusTest extends TestCase
{
    public function test_pesos_por_status(): void
    {
        $this->assertSame(1, PrioridadeStatus::EmExecucao->peso());
        $this->assertSame(2, PrioridadeStatus::AguardandoPagamento->peso());
        $this->assertSame(3, PrioridadeStatus::AguardandoAprovacao->peso());
        $this->assertSame(4, PrioridadeStatus::EmDiagnostico->peso());
        $this->assertSame(5, PrioridadeStatus::Recebida->peso());
    }

    public function test_mapa_de_peso_na_ordem_de_prioridade(): void
    {
        $this->assertSame([
            'Em execução' => 1,
            'Aguardando pagamento' => 2,
            'Aguardando aprovação' => 3,
            'Em diagnóstico' => 4,
            'Recebida' => 5,
        ], PrioridadeStatus::mapaDePeso());
    }

    public function test_status_excluidos_da_listagem(): void
    {
        $this->assertSame(['Finalizada', 'Entregue', 'Cancelada'], PrioridadeStatus::EXCLUIDOS_DA_LISTAGEM);
    }
}
