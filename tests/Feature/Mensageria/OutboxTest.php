<?php

namespace Tests\Feature\Mensageria;

use Domain\Shared\Messaging\Envelope;
use Infrastructure\Messaging\OutboxPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Infrastructure\Messaging\OutboxRelay;
use RuntimeException;
use Tests\Support\FakeBroker;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    private const SAGA_ID = '5d2a3c4e-1f2b-4c3d-9e8f-0a1b2c3d4e5f';

    private FakeBroker $broker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->broker = new FakeBroker();
    }

    private function relay(): OutboxRelay
    {
        return new OutboxRelay($this->broker, 'oficina');
    }

    private function envelope(string $type = 'billing.cmd.gerar-orcamento'): Envelope
    {
        return Envelope::criar($type, self::SAGA_ID, 'corr-1', 42, ['valor_total' => 1000]);
    }

    public function test_publicar_grava_na_outbox_sem_tocar_no_broker(): void
    {
        $envelope = $this->envelope();

        (new OutboxPublisher())->publicar($envelope);

        $linha = DB::table('outbox')->first();
        $this->assertSame($envelope->messageId, $linha->id);
        $this->assertSame('billing.cmd.gerar-orcamento', $linha->type);
        $this->assertSame(self::SAGA_ID, $linha->saga_id);
        $this->assertSame($envelope->paraJson(), $linha->corpo);
        $this->assertNull($linha->publicado_em);
        $this->assertSame([], $this->broker->publicadas);
    }

    public function test_transacao_revertida_nao_deixa_mensagem_na_outbox(): void
    {
        try {
            DB::transaction(function () {
                (new OutboxPublisher())->publicar($this->envelope());
                throw new RuntimeException('falha no caso de uso');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, DB::table('outbox')->count());
    }

    public function test_relay_publica_pendentes_em_ordem_e_marca_como_publicadas(): void
    {
        $publisher = new OutboxPublisher();
        $primeira = $this->envelope('billing.cmd.gerar-orcamento');
        $segunda = $this->envelope('execucao.cmd.enfileirar');
        $publisher->publicar($primeira);
        $this->travessiaDeTempo();
        $publisher->publicar($segunda);

        $publicadas = $this->relay()->publicarPendentes(10);

        $this->assertSame(2, $publicadas);
        $this->assertSame(
            ['billing.cmd.gerar-orcamento', 'execucao.cmd.enfileirar'],
            array_column($this->broker->publicadas, 'routing_key'),
        );
        $this->assertSame('oficina', $this->broker->publicadas[0]['exchange']);
        $this->assertSame($primeira->paraJson(), $this->broker->publicadas[0]['corpo']);
        $this->assertSame([
            'x-correlation-id' => 'corr-1',
            'x-saga-id' => self::SAGA_ID,
            'x-message-type' => 'billing.cmd.gerar-orcamento',
            'message_id' => $primeira->messageId,
        ], $this->broker->publicadas[0]['cabecalhos']);
        $this->assertSame(0, DB::table('outbox')->whereNull('publicado_em')->count());
    }

    public function test_relay_nao_republica_o_que_ja_foi_publicado(): void
    {
        (new OutboxPublisher())->publicar($this->envelope());
        $this->relay()->publicarPendentes(10);

        $publicadas = $this->relay()->publicarPendentes(10);

        $this->assertSame(0, $publicadas);
        $this->assertCount(1, $this->broker->publicadas);
    }

    public function test_relay_respeita_o_tamanho_do_lote(): void
    {
        $publisher = new OutboxPublisher();
        foreach (range(1, 3) as $_) {
            $publisher->publicar($this->envelope());
        }

        $this->assertSame(2, $this->relay()->publicarPendentes(2));
        $this->assertSame(1, DB::table('outbox')->whereNull('publicado_em')->count());
    }

    public function test_falha_no_broker_interrompe_o_lote_e_preserva_as_ja_publicadas(): void
    {
        Log::spy();
        $publisher = new OutboxPublisher();
        $primeira = $this->envelope();
        $publisher->publicar($primeira);
        $this->travessiaDeTempo();
        $segunda = $this->envelope();
        $publisher->publicar($segunda);
        $this->travessiaDeTempo();
        $publisher->publicar($this->envelope());
        $this->broker->falharNaPublicacao = 1;

        $publicadas = $this->relay()->publicarPendentes(10);

        $this->assertSame(1, $publicadas);
        $this->assertNotNull(DB::table('outbox')->where('id', $primeira->messageId)->value('publicado_em'));
        $this->assertNull(DB::table('outbox')->where('id', $segunda->messageId)->value('publicado_em'));
        $this->assertSame(2, DB::table('outbox')->whereNull('publicado_em')->count());
        Log::shouldHaveReceived('error')->withArgs(
            fn ($mensagem, $contexto) => $contexto['event_type'] === 'mensageria.outbox_falhou'
                && $contexto['message_id'] === $segunda->messageId
        )->once();
    }

    private function travessiaDeTempo(): void
    {
        $this->travel(1)->seconds();
    }
}
