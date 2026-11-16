<?php

namespace Tests\Feature\Saga;

use App\Mail\LinkDaSaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Infrastructure\Notificacao\EmailNotificadorCliente;
use RuntimeException;
use Tests\Concerns\CriaOSParaSaga;
use Tests\TestCase;

class EmailNotificadorClienteTest extends TestCase
{
    use RefreshDatabase, CriaOSParaSaga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo();
    }

    public function test_link_de_aprovacao_vai_para_o_email_do_cliente(): void
    {
        Mail::fake();
        $os = $this->criarOSComServico();

        (new EmailNotificadorCliente())->enviarLinkDeAprovacao($os->id, 'https://billing/aprovar', 123456);

        Mail::assertSent(LinkDaSaga::class, function (LinkDaSaga $m) use ($os) {
            $m->assertSeeInHtml('https://billing/aprovar');
            $m->assertSeeInHtml('R$ 1.234,56');
            $m->assertSeeInHtml('Ana Souza');

            return $m->hasTo('ana@exemplo.com')
                && $m->assunto === "Orçamento da OS #{$os->id}"
                && $m->acao === 'Ver e aprovar o orçamento';
        });
    }

    public function test_link_de_pagamento_tem_chamada_propria(): void
    {
        Mail::fake();
        $os = $this->criarOSComServico();

        (new EmailNotificadorCliente())->enviarLinkDePagamento($os->id, 'https://mp/pagar', 1000);

        Mail::assertSent(LinkDaSaga::class, fn (LinkDaSaga $m) => $m->assunto === "Pagamento da OS #{$os->id}"
            && $m->acao === 'Pagar com Mercado Pago'
            && $m->valor === 'R$ 10,00');
    }

    public function test_cliente_sem_email_nao_recebe_nada(): void
    {
        Mail::fake();
        Log::spy();
        $this->cliente->update(['email' => null]);
        $os = $this->criarOSComServico();

        (new EmailNotificadorCliente())->enviarLinkDeAprovacao($os->id, 'https://billing/aprovar', 1000);

        Mail::assertNothingSent();
        Log::shouldHaveReceived('info')->once();
    }

    public function test_falha_no_envio_nao_interrompe_a_saga(): void
    {
        Log::spy();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('provedor fora'));
        $os = $this->criarOSComServico();

        (new EmailNotificadorCliente())->enviarLinkDePagamento($os->id, 'https://mp/pagar', 1000);

        Log::shouldHaveReceived('error')->withArgs(fn ($mensagem) => str_contains($mensagem, 'provedor fora'))->once();
    }
}
