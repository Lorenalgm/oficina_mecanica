<?php

namespace Tests\Unit\Domain\Shared\Messaging;

use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Messaging\MensagemInvalida;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvelopeTest extends TestCase
{
    private const SAGA_ID = '5d2a3c4e-1f2b-4c3d-9e8f-0a1b2c3d4e5f';

    private function valido(array $alteracoes = []): array
    {
        return array_merge([
            'message_id' => '0b9f6c1e-2a3b-4c5d-8e9f-0a1b2c3d4e5f',
            'type' => 'billing.evt.orcamento-gerado',
            'version' => 1,
            'saga_id' => self::SAGA_ID,
            'correlation_id' => 'c41e-correlacao',
            'os_id' => 42,
            'occurred_at' => '2026-11-15T12:00:00Z',
            'payload' => ['orcamento_id' => 7, 'valor_total' => 15000],
        ], $alteracoes);
    }

    public function test_criar_preenche_metadados_do_contrato(): void
    {
        $envelope = Envelope::criar('billing.cmd.gerar-orcamento', self::SAGA_ID, 'corr-1', 42, ['valor_total' => 1000]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $envelope->messageId);
        $this->assertSame('billing.cmd.gerar-orcamento', $envelope->type);
        $this->assertSame(Envelope::VERSAO_ATUAL, $envelope->version);
        $this->assertSame(self::SAGA_ID, $envelope->sagaId);
        $this->assertSame('corr-1', $envelope->correlationId);
        $this->assertSame(42, $envelope->osId);
        $this->assertSame('+00:00', $envelope->occurredAt->format('P'));
        $this->assertSame(['valor_total' => 1000], $envelope->payload);
    }

    public function test_cada_envelope_tem_message_id_proprio(): void
    {
        $a = Envelope::criar('os.evt.teste', self::SAGA_ID, 'c', null);
        $b = Envelope::criar('os.evt.teste', self::SAGA_ID, 'c', null);

        $this->assertNotSame($a->messageId, $b->messageId);
    }

    public function test_ida_e_volta_pelo_json_preserva_os_campos(): void
    {
        $original = Envelope::deArray($this->valido());

        $copia = Envelope::deJson($original->paraJson());

        $this->assertEquals($original, $copia);
    }

    public function test_payload_vazio_vira_objeto_no_json(): void
    {
        $json = Envelope::criar('os.evt.teste', self::SAGA_ID, 'c', null)->paraJson();

        $this->assertStringContainsString('"payload":{}', $json);
        $this->assertStringContainsString('"os_id":null', $json);
    }

    public function test_cabecalhos_amqp_carregam_rastreamento(): void
    {
        $envelope = Envelope::deArray($this->valido());

        $this->assertSame([
            'x-correlation-id' => 'c41e-correlacao',
            'x-saga-id' => self::SAGA_ID,
            'x-message-type' => 'billing.evt.orcamento-gerado',
        ], $envelope->cabecalhos());
    }

    #[DataProvider('datasAceitas')]
    public function test_aceita_variacoes_de_iso_8601(string $data): void
    {
        $envelope = Envelope::deArray($this->valido(['occurred_at' => $data]));

        $this->assertSame('2026-11-15T12:00:00+00:00', $envelope->occurredAt->format(DATE_ATOM));
    }

    public static function datasAceitas(): array
    {
        return [
            'Z' => ['2026-11-15T12:00:00Z'],
            'offset' => ['2026-11-15T12:00:00+00:00'],
            'frações de segundo' => ['2026-11-15T12:00:00.123Z'],
        ];
    }

    #[DataProvider('envelopesInvalidos')]
    public function test_rejeita_envelope_fora_do_contrato(array $alteracoes, string $mensagem): void
    {
        $this->expectException(MensagemInvalida::class);
        $this->expectExceptionMessage($mensagem);

        Envelope::deArray($this->valido($alteracoes));
    }

    public static function envelopesInvalidos(): array
    {
        return [
            'message_id não UUID' => [['message_id' => 'abc'], 'message_id'],
            'type fora do padrão' => [['type' => 'billing.gerar'], 'type'],
            'type com serviço desconhecido' => [['type' => 'estoque.cmd.baixar'], 'type'],
            'versão futura' => [['version' => 2], 'Versão'],
            'versão como texto' => [['version' => '1'], 'Versão'],
            'saga_id não UUID' => [['saga_id' => 'saga'], 'saga_id'],
            'correlation_id vazio' => [['correlation_id' => ''], 'correlation_id'],
            'os_id negativo' => [['os_id' => -1], 'os_id'],
            'os_id texto' => [['os_id' => '42'], 'os_id'],
            'payload escalar' => [['payload' => 'x'], 'payload'],
            'data sem fuso' => [['occurred_at' => '2026-11-15 12:00:00'], 'occurred_at'],
            'data não texto' => [['occurred_at' => 123], 'occurred_at'],
        ];
    }

    public function test_rejeita_campo_obrigatorio_ausente(): void
    {
        $dados = $this->valido();
        unset($dados['saga_id']);

        $this->expectException(MensagemInvalida::class);
        $this->expectExceptionMessage('saga_id');

        Envelope::deArray($dados);
    }

    public function test_os_id_e_opcional(): void
    {
        $dados = $this->valido();
        unset($dados['os_id']);

        $this->assertNull(Envelope::deArray($dados)->osId);
    }

    public function test_rejeita_json_malformado(): void
    {
        $this->expectException(MensagemInvalida::class);

        Envelope::deJson('{nao é json');
    }

    public function test_rejeita_json_que_nao_e_objeto(): void
    {
        $this->expectException(MensagemInvalida::class);

        Envelope::deJson('"texto"');
    }
}
