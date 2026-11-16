<?php

namespace Domain\Shared\Messaging;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

final class Envelope
{
    public const VERSAO_ATUAL = 1;

    private const PADRAO_TIPO = '/^(os|billing|execucao)\.(cmd|evt)\.[a-z]+(-[a-z]+)*$/';
    private const PADRAO_UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private function __construct(
        public readonly string $messageId,
        public readonly string $type,
        public readonly int $version,
        public readonly string $sagaId,
        public readonly string $correlationId,
        public readonly ?int $osId,
        public readonly DateTimeImmutable $occurredAt,
        public readonly array $payload,
    ) {}

    public static function criar(
        string $type,
        string $sagaId,
        string $correlationId,
        ?int $osId,
        array $payload = [],
    ): self {
        return self::deArray([
            'message_id' => self::novoUuid(),
            'type' => $type,
            'version' => self::VERSAO_ATUAL,
            'saga_id' => $sagaId,
            'correlation_id' => $correlationId,
            'os_id' => $osId,
            'occurred_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
            'payload' => $payload,
        ]);
    }

    public static function deJson(string $json): self
    {
        try {
            $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            throw new MensagemInvalida('Corpo da mensagem não é um JSON válido.');
        }

        if (! is_array($dados)) {
            throw new MensagemInvalida('Corpo da mensagem não é um objeto JSON.');
        }

        return self::deArray($dados);
    }

    public static function deArray(array $dados): self
    {
        foreach (['message_id', 'type', 'version', 'saga_id', 'correlation_id', 'occurred_at', 'payload'] as $campo) {
            if (! array_key_exists($campo, $dados)) {
                throw new MensagemInvalida("Campo obrigatório ausente: {$campo}.");
            }
        }

        if (! is_string($dados['message_id']) || ! preg_match(self::PADRAO_UUID, $dados['message_id'])) {
            throw new MensagemInvalida('message_id deve ser um UUID.');
        }
        if (! is_string($dados['type']) || ! preg_match(self::PADRAO_TIPO, $dados['type'])) {
            throw new MensagemInvalida('type fora do padrão <servico>.<cmd|evt>.<nome>.');
        }
        if (! is_int($dados['version']) || $dados['version'] < 1 || $dados['version'] > self::VERSAO_ATUAL) {
            throw new MensagemInvalida("Versão {$dados['version']} não suportada.");
        }
        if (! is_string($dados['saga_id']) || ! preg_match(self::PADRAO_UUID, $dados['saga_id'])) {
            throw new MensagemInvalida('saga_id deve ser um UUID.');
        }
        if (! is_string($dados['correlation_id']) || $dados['correlation_id'] === '') {
            throw new MensagemInvalida('correlation_id é obrigatório.');
        }
        $osId = $dados['os_id'] ?? null;
        if ($osId !== null && (! is_int($osId) || $osId < 1)) {
            throw new MensagemInvalida('os_id deve ser um inteiro positivo.');
        }
        if (! is_array($dados['payload'])) {
            throw new MensagemInvalida('payload deve ser um objeto.');
        }

        $occurredAt = is_string($dados['occurred_at'])
            ? (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $dados['occurred_at'])
                ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $dados['occurred_at']))
            : false;
        if ($occurredAt === false) {
            throw new MensagemInvalida('occurred_at deve estar no formato ISO 8601.');
        }

        return new self(
            $dados['message_id'],
            $dados['type'],
            $dados['version'],
            $dados['saga_id'],
            $dados['correlation_id'],
            $osId,
            $occurredAt,
            $dados['payload'],
        );
    }

    public function paraArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'type' => $this->type,
            'version' => $this->version,
            'saga_id' => $this->sagaId,
            'correlation_id' => $this->correlationId,
            'os_id' => $this->osId,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'payload' => (object) $this->payload,
        ];
    }

    public function paraJson(): string
    {
        return json_encode($this->paraArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function cabecalhos(): array
    {
        return [
            'x-correlation-id' => $this->correlationId,
            'x-saga-id' => $this->sagaId,
            'x-message-type' => $this->type,
        ];
    }

    public static function novoUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
