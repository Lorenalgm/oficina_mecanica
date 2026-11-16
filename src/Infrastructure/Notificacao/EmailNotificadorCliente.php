<?php

namespace Infrastructure\Notificacao;

use App\Mail\LinkDaSaga;
use App\Models\OS;
use Domain\Atendimento\Saga\NotificadorCliente;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailNotificadorCliente implements NotificadorCliente
{
    public function enviarLinkDeAprovacao(int $osId, string $link, int $valorEmCentavos): void
    {
        $this->enviar(
            $osId,
            "Orçamento da OS #{$osId}",
            "O orçamento da sua Ordem de Serviço #{$osId} está pronto.",
            'Ver e aprovar o orçamento',
            $link,
            $valorEmCentavos,
        );
    }

    public function enviarLinkDePagamento(int $osId, string $link, int $valorEmCentavos): void
    {
        $this->enviar(
            $osId,
            "Pagamento da OS #{$osId}",
            "Orçamento aprovado. Falta só o pagamento da Ordem de Serviço #{$osId}.",
            'Pagar com Mercado Pago',
            $link,
            $valorEmCentavos,
        );
    }

    private function enviar(int $osId, string $assunto, string $chamada, string $acao, string $link, int $valorEmCentavos): void
    {
        $cliente = OS::with('cliente')->find($osId)?->cliente;
        if (! $cliente || empty($cliente->email)) {
            Log::info("OS #{$osId}: cliente sem e-mail cadastrado; link não enviado.");

            return;
        }

        try {
            Mail::to($cliente->email)->send(new LinkDaSaga(
                osId: $osId,
                assunto: $assunto,
                chamada: $chamada,
                acao: $acao,
                link: $link,
                valor: 'R$ '.number_format($valorEmCentavos / 100, 2, ',', '.'),
                clienteNome: $cliente->nome,
            ));
        } catch (Throwable $e) {
            Log::error("OS #{$osId}: falha ao enviar e-mail com link: {$e->getMessage()}");
        }
    }
}
