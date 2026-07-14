<?php

namespace App\Listeners;

use App\Mail\StatusOSAtualizado;
use App\Models\Cliente;
use App\Models\Status;
use Domain\Atendimento\Events\StatusOSAlterado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarEmailStatusOS
{
    public function handle(StatusOSAlterado $event): void
    {
        $os = $event->os;
        $cliente = Cliente::find($os->getClienteId());

        if (!$cliente || empty($cliente->email)) {
            Log::info("OS #{$os->getId()}: cliente sem e-mail cadastrado; notificação de status não enviada.");

            return;
        }

        $status = Status::find($event->statusNovoId);
        $statusNome = $status?->nome ?? (string) $event->statusNovoId;

        // A notificação é um efeito secundário: uma falha no envio (chave inválida,
        // domínio não verificado, indisponibilidade do provedor) não deve quebrar a
        // troca de status da OS. Registramos o erro e seguimos.
        try {
            Mail::to($cliente->email)->send(new StatusOSAtualizado(
                osId: (int) $os->getId(),
                statusNome: $statusNome,
                clienteNome: $cliente->nome,
            ));
        } catch (\Throwable $e) {
            Log::error("OS #{$os->getId()}: falha ao enviar e-mail de status: {$e->getMessage()}");
        }
    }
}
