<?php

namespace Tests\Unit\Domain\Atendimento\Entities;

use Carbon\Carbon;
use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Entities\OSStatus;
use Domain\Atendimento\Enums\StatusOrcamento;
use PHPUnit\Framework\TestCase;

class OSEntidadesTest extends TestCase
{
    // OSOrcamento

    public function test_orcamento_is_pendente_quando_status_pendente(): void
    {
        $orc = new OSOrcamento(1, 1, 100.0, Carbon::now(), null, StatusOrcamento::Pendente);
        $this->assertTrue($orc->isPendente());
    }

    public function test_orcamento_nao_is_pendente_quando_aprovado(): void
    {
        $orc = new OSOrcamento(1, 1, 100.0, Carbon::now(), null, StatusOrcamento::Aprovado);
        $this->assertFalse($orc->isPendente());
    }

    public function test_orcamento_aprovar_altera_status_e_define_data(): void
    {
        $orc = new OSOrcamento(1, 1, 150.0, Carbon::now(), null, StatusOrcamento::Pendente);
        $dataAprovacao = Carbon::now();

        $orc->aprovar($dataAprovacao);

        $this->assertEquals(StatusOrcamento::Aprovado, $orc->getStatus());
        $this->assertSame($dataAprovacao, $orc->getDataAprovacao());
        $this->assertFalse($orc->isPendente());
    }

    public function test_orcamento_recusar_altera_status(): void
    {
        $orc = new OSOrcamento(1, 1, 150.0, Carbon::now(), null, StatusOrcamento::Pendente);

        $orc->recusar();

        $this->assertEquals(StatusOrcamento::Recusado, $orc->getStatus());
        $this->assertFalse($orc->isPendente());
    }

    public function test_orcamento_getters_basicos(): void
    {
        $data = Carbon::now();
        $orc = new OSOrcamento(5, 3, 200.0, $data, null, StatusOrcamento::Pendente);

        $this->assertEquals(5, $orc->getId());
        $this->assertEquals(3, $orc->getOsId());
        $this->assertEquals(200.0, $orc->getValorTotal());
        $this->assertSame($data, $orc->getDataOrcamento());
        $this->assertNull($orc->getDataAprovacao());
    }

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
