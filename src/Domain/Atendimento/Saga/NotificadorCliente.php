<?php

namespace Domain\Atendimento\Saga;

interface NotificadorCliente
{
    public function enviarLinkDeAprovacao(int $osId, string $link, int $valorEmCentavos): void;

    public function enviarLinkDePagamento(int $osId, string $link, int $valorEmCentavos): void;
}
