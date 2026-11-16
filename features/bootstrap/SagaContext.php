<?php

use App\Mail\LinkDaSaga;
use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\OS;
use App\Models\OSServico;
use App\Models\OSServicoInsumo;
use App\Models\Servico;
use App\Models\Status;
use App\Models\Veiculo;
use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Database\Seeders\StatusSeeder;
use Domain\Shared\Messaging\Envelope;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Infrastructure\Messaging\Decisao;
use Infrastructure\Messaging\ProcessadorDeMensagens;
use PHPUnit\Framework\Assert;

class SagaContext implements Context
{
    private const AMBIENTE = [
        'APP_ENV' => 'testing',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'BCRYPT_ROUNDS' => '4',
        'CACHE_STORE' => 'array',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'JWT_SECRET' => 'segredo-de-teste-apenas-para-a-suite',
        'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];

    private Application $app;
    private array $servicos = [];
    private array $insumos = [];
    private OS $os;
    private ?string $sagaId = null;

    #[BeforeScenario]
    public function iniciarAplicacao(): void
    {
        foreach (self::AMBIENTE as $chave => $valor) {
            putenv("{$chave}={$valor}");
            $_ENV[$chave] = $_SERVER[$chave] = $valor;
        }

        Facade::clearResolvedInstances();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(ConsoleKernel::class)->bootstrap();

        Artisan::call('migrate', ['--force' => true]);
        (new StatusSeeder())->run();
        Mail::fake();
    }

    #[AfterScenario]
    public function encerrarAplicacao(): void
    {
        $this->app->flush();
    }

    #[Given('/^o serviço "([^"]+)" de R\$ ([\d.,]+)$/')]
    public function oServico(string $nome, string $valor): void
    {
        $this->servicos[$nome] = Servico::factory()->create(['nome' => $nome, 'valor' => $this->reais($valor)]);
    }

    #[Given('/^o insumo "([^"]+)" de R\$ ([\d.,]+) com (\d+) unidades em estoque$/')]
    public function oInsumo(string $nome, string $valor, int $estoque): void
    {
        $this->insumos[$nome] = Insumo::factory()->create([
            'nome' => $nome,
            'valor' => $this->reais($valor),
            'quantidade_estoque' => $estoque,
        ]);
    }

    #[Given('/^uma OS da cliente "([^"]+)" com "([^"]+)" usando (\d+) "([^"]+)"$/')]
    public function umaOS(string $cliente, string $servico, int $quantidade, string $insumo): void
    {
        $dono = Cliente::factory()->create(['nome' => $cliente, 'email' => 'cliente@exemplo.com']);
        $this->os = OS::create([
            'cliente_id' => $dono->id,
            'veiculo_id' => Veiculo::factory()->create(['cliente_id' => $dono->id])->id,
            'status_atual_id' => Status::where('nome', 'Recebida')->value('id'),
            'descricao_problema' => 'Revisão',
        ]);
        $osServico = OSServico::create(['os_id' => $this->os->id, 'servico_id' => $this->servicos[$servico]->id]);
        OSServicoInsumo::create([
            'os_servico_id' => $osServico->id,
            'insumo_id' => $this->insumos[$insumo]->id,
            'quantidade' => $quantidade,
        ]);
    }

    #[Given('/^que o estoque de "([^"]+)" é de (\d+) unidades?$/')]
    public function queOEstoqueE(string $insumo, int $quantidade): void
    {
        $this->insumos[$insumo]->update(['quantidade_estoque' => $quantidade]);
    }

    #[Given('/^que a Saga chegou até "([^"]+)"$/')]
    public function queASagaChegouAte(string $etapa): void
    {
        $passos = [
            'pedido de orçamento' => fn () => $this->oAtendenteSolicitaOOrcamento(),
            'orçamento gerado' => fn () => $this->oBillingGeraOOrcamento(),
            'orçamento aprovado' => fn () => $this->aClienteAprovaOOrcamento(),
            'cobrança criada' => fn () => $this->oBillingCriaACobranca(),
            'pagamento confirmado' => fn () => $this->oPagamentoEConfirmado(),
        ];
        Assert::assertArrayHasKey($etapa, $passos);

        foreach ($passos as $nome => $passo) {
            $passo();
            if ($nome === $etapa) {
                return;
            }
        }
    }

    #[When('o atendente solicita o orçamento')]
    public function oAtendenteSolicitaOOrcamento(): void
    {
        $token = JWT::encode(
            ['iss' => config('jwt.issuer'), 'sub' => '1', 'iat' => time(), 'exp' => time() + 3600],
            config('jwt.secret'),
            config('jwt.algo'),
        );
        $requisicao = Request::create("/api/os/{$this->os->id}/orcamento", 'POST', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $kernel = $this->app->make(HttpKernel::class);
        $resposta = $kernel->handle($requisicao);
        $kernel->terminate($requisicao, $resposta);

        Assert::assertSame(202, $resposta->getStatusCode(), $resposta->getContent());
        $this->sagaId = json_decode($resposta->getContent(), true)['data']['saga_id'];
    }

    #[When('o Billing gera o orçamento')]
    public function oBillingGeraOOrcamento(): void
    {
        $this->evento('billing.evt.orcamento-gerado', [
            'orcamento_id' => 7,
            'valor_total' => $this->ultimoComando('billing.cmd.gerar-orcamento')->payload['valor_total'],
            'link_aprovacao' => 'https://billing.oficina/orcamentos/7/aprovar?token=abc',
        ]);
    }

    #[When('o Billing não consegue gerar o orçamento')]
    public function oBillingNaoConsegueGerarOOrcamento(): void
    {
        $this->evento('billing.evt.orcamento-falhou', ['motivo' => 'serviço inativo']);
    }

    #[When('a cliente aprova o orçamento')]
    public function aClienteAprovaOOrcamento(): void
    {
        $this->evento('billing.evt.orcamento-aprovado', ['orcamento_id' => 7, 'aprovado_em' => now()->toAtomString()]);
    }

    #[When('a cliente recusa o orçamento')]
    public function aClienteRecusaOOrcamento(): void
    {
        $this->evento('billing.evt.orcamento-recusado', ['orcamento_id' => 7, 'recusado_em' => now()->toAtomString()]);
    }

    #[When('o Billing cria a cobrança')]
    public function oBillingCriaACobranca(): void
    {
        $this->evento('billing.evt.cobranca-criada', ['pagamento_id' => 9, 'link_pagamento' => 'https://mercadopago/checkout/9']);
    }

    #[When('o pagamento é confirmado')]
    public function oPagamentoEConfirmado(): void
    {
        $this->evento('billing.evt.pagamento-confirmado', ['pagamento_id' => 9, 'valor' => 19980, 'pago_em' => now()->toAtomString(), 'gateway_id' => 'mp-1']);
    }

    #[When('o pagamento é recusado')]
    public function oPagamentoERecusado(): void
    {
        $this->evento('billing.evt.pagamento-recusado', ['pagamento_id' => 9, 'motivo' => 'cartão recusado']);
    }

    #[When('a Execução enfileira a OS')]
    public function aExecucaoEnfileiraAOS(): void
    {
        $this->evento('execucao.evt.enfileirada', ['execucao_id' => 'exec-1', 'posicao_na_fila' => 1]);
    }

    #[When('a Execução rejeita a OS por fila cheia')]
    public function aExecucaoRejeitaAOS(): void
    {
        $this->evento('execucao.evt.rejeitada', ['motivo' => 'capacidade_esgotada']);
    }

    #[When('a Execução finaliza o reparo')]
    public function aExecucaoFinalizaOReparo(): void
    {
        $this->evento('execucao.evt.status-alterado', ['execucao_id' => 'exec-1', 'status_anterior' => 'em_diagnostico', 'status' => 'em_reparo']);
        $this->evento('execucao.evt.finalizada', ['execucao_id' => 'exec-1', 'finalizada_em' => now()->toAtomString()]);
    }

    #[When('o Billing confirma as compensações pedidas')]
    public function oBillingConfirmaAsCompensacoes(): void
    {
        $respostas = [
            'billing.cmd.estornar' => ['billing.evt.pagamento-estornado', ['pagamento_id' => 9]],
            'billing.cmd.cancelar-orcamento' => ['billing.evt.orcamento-cancelado', ['orcamento_id' => 7]],
        ];

        $respondidos = [];
        while ($this->estadoDaSaga() === 'compensando') {
            $comando = $this->ultimoComando();
            Assert::assertArrayHasKey($comando->type, $respostas, "Compensação inesperada: {$comando->type}");
            Assert::assertNotContains($comando->messageId, $respondidos, 'A Saga não avançou após a confirmação.');
            $respondidos[] = $comando->messageId;

            [$type, $payload] = $respostas[$comando->type];
            $this->evento($type, $payload);
        }
    }

    #[When('/^o estorno falha (\d+) vezes$/')]
    public function oEstornoFalha(int $vezes): void
    {
        for ($i = 0; $i < $vezes; $i++) {
            $this->evento('billing.evt.estorno-falhou', ['pagamento_id' => 9, 'motivo' => 'Mercado Pago indisponível']);
        }
    }

    #[Then('/^a OS fica "([^"]+)"$/')]
    public function aOSFica(string $status): void
    {
        Assert::assertSame($status, OS::with('statusAtual')->find($this->os->id)->statusAtual->nome);
    }

    #[Then('/^a Saga fica "([^"]+)"$/')]
    public function aSagaFica(string $estado): void
    {
        Assert::assertSame($estado, $this->estadoDaSaga());
    }

    #[Then('/^o estoque de "([^"]+)" fica em (\d+)$/')]
    public function oEstoqueFicaEm(string $insumo, int $quantidade): void
    {
        Assert::assertSame($quantidade, Insumo::find($this->insumos[$insumo]->id)->quantidade_estoque);
    }

    #[Then('/^o Billing recebe o pedido de orçamento de R\$ ([\d.,]+)$/')]
    public function oBillingRecebeOPedidoDeOrcamento(string $valor): void
    {
        $comando = $this->ultimoComando('billing.cmd.gerar-orcamento');
        Assert::assertSame($this->centavos($valor), $comando->payload['valor_total']);
        Assert::assertCount(2, $comando->payload['itens']);
    }

    #[Then('/^o Billing recebe o pedido de cobrança de R\$ ([\d.,]+)$/')]
    public function oBillingRecebeOPedidoDeCobranca(string $valor): void
    {
        Assert::assertSame($this->centavos($valor), $this->ultimoComando('billing.cmd.cobrar')->payload['valor']);
    }

    #[Then('/^a Execução recebe a OS na fila da filial "([^"]+)"$/')]
    public function aExecucaoRecebeAOS(string $filial): void
    {
        Assert::assertSame($filial, $this->ultimoComando('execucao.cmd.enfileirar')->payload['filial']);
    }

    #[Then('/^a cliente recebe por e-mail o link de (aprovação|pagamento)$/')]
    public function aClienteRecebeOLink(string $tipo): void
    {
        $link = $tipo === 'aprovação' ? 'https://billing.oficina/orcamentos/7/aprovar?token=abc' : 'https://mercadopago/checkout/9';

        Mail::assertSent(LinkDaSaga::class, fn (LinkDaSaga $m) => $m->hasTo('cliente@exemplo.com') && $m->link === $link);
    }

    #[Then('/^as compensações enviadas ao Billing foram "([^"]+)"$/')]
    public function asCompensacoesEnviadas(string $lista): void
    {
        $esperadas = $lista === 'nenhuma' ? [] : array_map(fn ($c) => "billing.cmd.{$c}", array_map('trim', explode(',', $lista)));
        $enviadas = array_values(array_filter(
            array_map(fn (Envelope $e) => $e->type, $this->comandos()),
            fn (string $type) => in_array($type, ['billing.cmd.estornar', 'billing.cmd.cancelar-orcamento'], true),
        ));

        Assert::assertSame($esperadas, $enviadas);
    }

    private function evento(string $type, array $payload): void
    {
        Assert::assertNotNull($this->sagaId, 'A Saga ainda não foi iniciada.');
        $envelope = Envelope::criar($type, $this->sagaId, 'corr-bdd', $this->os->id, $payload);

        $decisao = $this->app->make(ProcessadorDeMensagens::class)->processar($envelope->paraJson(), 0);

        Assert::assertSame(Decisao::Confirmar, $decisao, "O OS Service não confirmou {$type}.");
    }

    /** @return list<Envelope> */
    private function comandos(): array
    {
        return DB::table('outbox')->orderBy('id')->pluck('corpo')
            ->map(fn (string $corpo) => Envelope::deJson($corpo))->all();
    }

    private function ultimoComando(?string $type = null): Envelope
    {
        $comandos = array_values(array_filter($this->comandos(), fn (Envelope $e) => $type === null || $e->type === $type));
        Assert::assertNotEmpty($comandos, 'Nenhum comando '.($type ?? '').' na outbox.');

        return end($comandos);
    }

    private function estadoDaSaga(): string
    {
        return DB::table('sagas')->where('id', $this->sagaId)->value('estado');
    }

    private function reais(string $valor): float
    {
        return (float) str_replace(['.', ','], ['', '.'], $valor);
    }

    private function centavos(string $valor): int
    {
        return (int) round($this->reais($valor) * 100);
    }
}
