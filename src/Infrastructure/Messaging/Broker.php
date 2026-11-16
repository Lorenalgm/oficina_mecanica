<?php

namespace Infrastructure\Messaging;

interface Broker
{
    /**
     * Publica e aguarda a confirmação do broker.
     *
     * @throws FalhaAoPublicar quando o broker recusa ou não consegue rotear a mensagem
     */
    public function publicar(string $exchange, string $routingKey, string $corpo, array $cabecalhos = []): void;

    /**
     * Consome a fila até $continuar devolver false.
     *
     * @param callable(string $corpo, int $tentativas): Decisao $tratar
     * @param callable(): bool $continuar
     */
    public function consumir(string $fila, callable $tratar, callable $continuar): void;
}
