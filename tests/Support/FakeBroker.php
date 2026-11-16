<?php

namespace Tests\Support;

use Infrastructure\Messaging\Broker;
use Infrastructure\Messaging\Decisao;
use Infrastructure\Messaging\FalhaAoPublicar;

class FakeBroker implements Broker
{
    /** @var list<array{exchange: string, routing_key: string, corpo: string, cabecalhos: array}> */
    public array $publicadas = [];

    /** @var list<array{corpo: string, tentativas: int}> */
    public array $entrada = [];

    /** @var list<Decisao> */
    public array $decisoes = [];

    public ?int $falharNaPublicacao = null;

    public function publicar(string $exchange, string $routingKey, string $corpo, array $cabecalhos = []): void
    {
        if ($this->falharNaPublicacao === count($this->publicadas)) {
            throw new FalhaAoPublicar('broker indisponível');
        }

        $this->publicadas[] = [
            'exchange' => $exchange,
            'routing_key' => $routingKey,
            'corpo' => $corpo,
            'cabecalhos' => $cabecalhos,
        ];
    }

    public function enfileirar(string $corpo, int $tentativas = 0): void
    {
        $this->entrada[] = ['corpo' => $corpo, 'tentativas' => $tentativas];
    }

    public function consumir(string $fila, callable $tratar, callable $continuar): void
    {
        while ($this->entrada !== [] && $continuar()) {
            $mensagem = array_shift($this->entrada);
            $this->decisoes[] = $tratar($mensagem['corpo'], $mensagem['tentativas']);
        }
    }
}
