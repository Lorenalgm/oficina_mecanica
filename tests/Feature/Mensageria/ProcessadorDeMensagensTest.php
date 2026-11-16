<?php

namespace Tests\Feature\Mensageria;

use App\Support\CorrelationContext;
use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Messaging\MessageHandler;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Infrastructure\Messaging\Decisao;
use Infrastructure\Messaging\HandlerRegistry;
use Infrastructure\Messaging\ProcessadorDeMensagens;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class ProcessadorDeMensagensTest extends TestCase
{
    use RefreshDatabase;

    private const SAGA_ID = '5d2a3c4e-1f2b-4c3d-9e8f-0a1b2c3d4e5f';
    private const TIPO = 'billing.evt.orcamento-gerado';

    private HandlerEspiao $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new HandlerEspiao();
        Log::spy();
    }

    protected function tearDown(): void
    {
        CorrelationContext::clear();
        parent::tearDown();
    }

    private function processador(): ProcessadorDeMensagens
    {
        $registro = new HandlerRegistry([self::TIPO => HandlerEspiao::class], fn () => $this->handler);

        return new ProcessadorDeMensagens($registro, 3);
    }

    private function envelope(string $type = self::TIPO): Envelope
    {
        return Envelope::criar($type, self::SAGA_ID, 'corr-da-saga', 42, ['orcamento_id' => 7]);
    }

    private function logou(string $nivel, string $eventType): void
    {
        Log::shouldHaveReceived($nivel)->withArgs(
            fn ($mensagem, $contexto = []) => ($contexto['event_type'] ?? null) === $eventType
        )->atLeast()->once();
    }

    public function test_mensagem_valida_e_entregue_ao_handler_e_registrada(): void
    {
        $envelope = $this->envelope();

        $decisao = $this->processador()->processar($envelope->paraJson(), 0);

        $this->assertSame(Decisao::Confirmar, $decisao);
        $this->assertEquals([$envelope], $this->handler->recebidas);
        $this->assertDatabaseHas('mensagens_processadas', [
            'message_id' => $envelope->messageId,
            'type' => self::TIPO,
            'saga_id' => self::SAGA_ID,
        ]);
        $this->logou('info', 'mensageria.processada');
    }

    public function test_handler_enxerga_o_correlation_id_da_saga_e_o_contexto_e_limpo_depois(): void
    {
        $this->processador()->processar($this->envelope()->paraJson(), 0);

        $this->assertSame('corr-da-saga', $this->handler->correlationIdVisto);
        $this->assertNull(CorrelationContext::get());
    }

    public function test_mensagem_repetida_e_confirmada_sem_reexecutar_o_efeito(): void
    {
        $corpo = $this->envelope()->paraJson();
        $this->processador()->processar($corpo, 0);

        $decisao = $this->processador()->processar($corpo, 0);

        $this->assertSame(Decisao::Confirmar, $decisao);
        $this->assertCount(1, $this->handler->recebidas);
        $this->assertSame(1, DB::table('mensagens_processadas')->count());
        $this->logou('info', 'mensageria.duplicada');
    }

    public function test_corrida_entre_consumidores_vira_duplicata_confirmada(): void
    {
        $envelope = $this->envelope();
        Event::listen(TransactionRolledBack::class, function () use ($envelope) {
            if (DB::table('mensagens_processadas')->where('message_id', $envelope->messageId)->doesntExist()) {
                $this->registrarProcessada($envelope->messageId);
            }
        });
        $this->handler->aoTratar = fn () => throw new UniqueConstraintViolationException(
            'sqlite', 'insert into "mensagens_processadas"', [], new PDOException('UNIQUE constraint failed'),
        );

        $decisao = $this->processador()->processar($envelope->paraJson(), 0);

        $this->assertSame(Decisao::Confirmar, $decisao);
        $this->logou('info', 'mensageria.duplicada');
    }

    public function test_violacao_de_unicidade_do_proprio_handler_nao_e_tratada_como_duplicata(): void
    {
        $outraMensagem = Envelope::novoUuid();
        $this->registrarProcessada($outraMensagem);
        $this->handler->aoTratar = fn () => $this->registrarProcessada($outraMensagem);

        $decisao = $this->processador()->processar($this->envelope()->paraJson(), 0);

        $this->assertSame(Decisao::Retentar, $decisao);
        $this->assertSame(1, DB::table('mensagens_processadas')->count());
    }

    private function registrarProcessada(string $messageId): void
    {
        DB::table('mensagens_processadas')->insert([
            'message_id' => $messageId,
            'type' => self::TIPO,
            'saga_id' => self::SAGA_ID,
            'processado_em' => now(),
        ]);
    }

    public function test_falha_no_handler_desfaz_o_registro_e_pede_retentativa(): void
    {
        $this->handler->aoTratar = function () {
            DB::table('outbox')->insert([
                'message_id' => Envelope::novoUuid(),
                'type' => 'billing.cmd.cobrar',
                'saga_id' => self::SAGA_ID,
                'corpo' => '{}',
                'criado_em' => now(),
            ]);
            throw new RuntimeException('banco fora');
        };

        $decisao = $this->processador()->processar($this->envelope()->paraJson(), 0);

        $this->assertSame(Decisao::Retentar, $decisao);
        $this->assertSame(0, DB::table('mensagens_processadas')->count());
        $this->assertSame(0, DB::table('outbox')->count());
        Log::shouldHaveReceived('log')->withArgs(
            fn ($nivel, $mensagem, $contexto) => $nivel === 'warning'
                && $contexto['event_type'] === 'mensageria.retentativa'
                && $contexto['tentativa'] === 1
        )->once();
    }

    public function test_ultima_tentativa_falhando_envia_para_a_dlq(): void
    {
        $this->handler->aoTratar = fn () => throw new RuntimeException('banco fora');

        $decisao = $this->processador()->processar($this->envelope()->paraJson(), 2);

        $this->assertSame(Decisao::Rejeitar, $decisao);
        Log::shouldHaveReceived('log')->withArgs(
            fn ($nivel, $mensagem, $contexto) => $nivel === 'error'
                && $contexto['event_type'] === 'mensageria.enviada_dlq'
                && $contexto['tentativa'] === 3
                && $contexto['saga_id'] === self::SAGA_ID
        )->once();
    }

    public function test_mensagem_invalida_vai_direto_para_a_dlq(): void
    {
        $decisao = $this->processador()->processar('{"type":"lixo"}', 0);

        $this->assertSame(Decisao::Rejeitar, $decisao);
        $this->assertSame([], $this->handler->recebidas);
        $this->logou('warning', 'mensageria.mensagem_invalida');
    }

    public function test_tipo_sem_handler_vai_para_a_dlq(): void
    {
        $decisao = $this->processador()->processar($this->envelope('execucao.evt.finalizada')->paraJson(), 0);

        $this->assertSame(Decisao::Rejeitar, $decisao);
        $this->assertSame(0, DB::table('mensagens_processadas')->count());
        $this->logou('error', 'mensageria.tipo_desconhecido');
    }
}

class HandlerEspiao implements MessageHandler
{
    /** @var list<Envelope> */
    public array $recebidas = [];

    public ?string $correlationIdVisto = null;

    public ?\Closure $aoTratar = null;

    public function tratar(Envelope $envelope): void
    {
        $this->correlationIdVisto = CorrelationContext::get();
        if ($this->aoTratar !== null) {
            ($this->aoTratar)($envelope);
        }
        $this->recebidas[] = $envelope;
    }
}
