<?php

namespace Tests\Unit\Domain\Atendimento\Entities;

use Carbon\Carbon;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Entities\OSStatus;
use PHPUnit\Framework\TestCase;

class OSEntidadesTest extends TestCase
{
    // OSServico

    public function test_os_servico_getters_e_setters(): void
    {
        $sv = new OSServico(id: 1, osId: 2, servicoId: 3);

        $this->assertEquals(1, $sv->getId());
        $this->assertEquals(2, $sv->getOsId());
        $this->assertEquals(3, $sv->getServicoId());
        $this->assertNull($sv->getServicoNome());
        $this->assertNull($sv->getServicoValor());
        $this->assertNull($sv->getInsumos());

        $sv->setServicoNome('Troca de óleo');
        $sv->setServicoValor(99.90);
        $sv->setInsumos([]);

        $this->assertEquals('Troca de óleo', $sv->getServicoNome());
        $this->assertEquals(99.90, $sv->getServicoValor());
        $this->assertSame([], $sv->getInsumos());
    }

    // OSServicoInsumo

    public function test_os_servico_insumo_getters_e_setters(): void
    {
        $osi = new OSServicoInsumo(id: 10, osServicoId: 1, insumoId: 5, quantidade: 3);

        $this->assertEquals(10, $osi->getId());
        $this->assertEquals(1, $osi->getOsServicoId());
        $this->assertEquals(5, $osi->getInsumoId());
        $this->assertEquals(3, $osi->getQuantidade());
        $this->assertNull($osi->getInsumoNome());
        $this->assertNull($osi->getInsumoValor());

        $osi->setInsumoNome('Filtro de ar');
        $osi->setInsumoValor(45.50);

        $this->assertEquals('Filtro de ar', $osi->getInsumoNome());
        $this->assertEquals(45.50, $osi->getInsumoValor());
    }

    // OSStatus

    public function test_os_status_getters_e_setter(): void
    {
        $data = Carbon::now();
        $st = new OSStatus(id: 7, osId: 2, statusId: 1, dataStatus: $data);

        $this->assertEquals(7, $st->getId());
        $this->assertEquals(2, $st->getOsId());
        $this->assertEquals(1, $st->getStatusId());
        $this->assertSame($data, $st->getDataStatus());
        $this->assertNull($st->getStatusNome());

        $st->setStatusNome('Recebida');

        $this->assertEquals('Recebida', $st->getStatusNome());
    }
}
