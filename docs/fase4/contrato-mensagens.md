# Contrato de mensagens — Fase 4

Fonte única do que trafega no RabbitMQ entre OS Service, Billing Service e
Execução Service. Qualquer mudança aqui vale para os três repositórios.

## Topologia

| Recurso | Tipo | Ligação (routing key) |
|---|---|---|
| `oficina` | exchange `topic`, durável | — |
| `oficina.retry` | exchange `direct`, durável | routing key = nome da fila de trabalho |
| `oficina.dlx` | exchange `direct`, durável | routing key = nome da fila de trabalho |
| `billing.comandos` | fila (consumida pelo Billing) | `billing.cmd.#` |
| `execucao.comandos` | fila (consumida pela Execução) | `execucao.cmd.#` |
| `os.saga.eventos` | fila (consumida pelo OS) | `billing.evt.#`, `execucao.evt.#` |
| `<fila>.retry` | fila com `x-message-ttl` 5 s; ao expirar, volta para `<fila>` pelo exchange padrão | ligada ao `oficina.retry` |
| `<fila>.dlq` | fila final das mensagens rejeitadas (`<fila>` tem `x-dead-letter-exchange = oficina.dlx`) | ligada ao `oficina.dlx` |

- A routing key é sempre igual ao `type` da mensagem.
- Mensagens persistentes (`delivery_mode = 2`), publicadas com *publisher confirms*.
- Vhost `oficina`. Um usuário do RabbitMQ por serviço (`os`, `billing`,
  `execucao`): publica só em `oficina` e `oficina.retry`, lê só a própria fila
  e não declara nada. A topologia é criada pelo `oficina-infra-k8s`.
- Clientes AMQP precisam negociar `frame_max` ≥ 8192 (exigência do RabbitMQ 4.1).

## Envelope (v1)

```json
{
  "message_id": "0b9f6c1e-…",
  "type": "billing.cmd.gerar-orcamento",
  "version": 1,
  "saga_id": "5d2a…",
  "correlation_id": "c41e…",
  "os_id": 42,
  "occurred_at": "2026-11-15T12:00:00Z",
  "payload": {}
}
```

Cabeçalhos AMQP repetidos para o rastreamento sem abrir o corpo:
`x-correlation-id`, `x-saga-id`, `x-message-type`.

Regras:

- `correlation_id` nasce no `X-Correlation-Id` da requisição HTTP que iniciou a
  Saga e segue em toda mensagem e log dos três serviços.
- O payload leva um **snapshot** do que o destino precisa (itens com preço,
  e-mail do cliente). Nenhum serviço consulta outro durante a Saga.
- Valores monetários em **centavos** (`int`), para não perder precisão.
- Mudança incompatível vira um novo `version`. Os consumidores aceitam a versão
  atual e a anterior.

## Entrega, retentativa e idempotência

- **Outbox:** a mensagem é gravada junto com o efeito, na mesma transação
  (tabela `outbox` no Postgres; no Mongo, array `outbox` dentro do próprio
  documento da execução). Um processo publicador envia e marca como publicada.
- **Idempotência:** o consumidor grava o `message_id` em
  `mensagens_processadas` (ou `processadas` no documento Mongo) na mesma
  transação do efeito. Se a mensagem se repetir, ele dá ack sem repetir o efeito.
- **Retentativa:** em erro técnico, o consumidor republica a mensagem no
  exchange `oficina.retry` (routing key = nome da própria fila) com o cabeçalho
  `x-tentativas` incrementado e dá ack; ela volta depois de 5 s. Na 3ª falha,
  ou se a mensagem for inválida, o consumidor dá `nack` sem requeue e o broker
  a envia para a `.dlq`.
- **Erro de negócio não é retentado:** vira um evento de falha
  (`*-falhou`, `rejeitada`) e a Saga decide o que fazer.
- **Fora de ordem:** um evento que não cabe no estado atual da Saga (atrasado,
  duplicado ou de uma Saga já encerrada) é ignorado com log `warning` e ack.

## Mensagens

### Billing

| Tipo | Quem publica | Payload |
|---|---|---|
| `billing.cmd.gerar-orcamento` | OS | `cliente {id, nome, email, documento}`, `veiculo {placa}`, `itens [{tipo: servico\|insumo, referencia_id, descricao, quantidade, valor_unitario}]`, `valor_total` |
| `billing.evt.orcamento-gerado` | Billing | `orcamento_id`, `valor_total`, `link_aprovacao` |
| `billing.evt.orcamento-falhou` | Billing | `motivo` |
| `billing.evt.orcamento-aprovado` | Billing | `orcamento_id`, `aprovado_em` |
| `billing.evt.orcamento-recusado` | Billing | `orcamento_id`, `recusado_em` |
| `billing.cmd.cancelar-orcamento` | OS | `motivo` |
| `billing.evt.orcamento-cancelado` | Billing | `orcamento_id` |
| `billing.cmd.cobrar` | OS | `orcamento_id`, `valor`, `pagador {nome, email, documento}` |
| `billing.evt.cobranca-criada` | Billing | `pagamento_id`, `link_pagamento` |
| `billing.evt.pagamento-confirmado` | Billing | `pagamento_id`, `valor`, `pago_em`, `gateway_id` |
| `billing.evt.pagamento-recusado` | Billing | `pagamento_id`, `motivo` |
| `billing.cmd.estornar` | OS | `pagamento_id`, `motivo` |
| `billing.evt.pagamento-estornado` | Billing | `pagamento_id`, `estornado_em` |
| `billing.evt.estorno-falhou` | Billing | `pagamento_id`, `motivo` |

### Execução

| Tipo | Quem publica | Payload |
|---|---|---|
| `execucao.cmd.enfileirar` | OS | `filial`, `veiculo {placa, modelo}`, `servicos [{descricao}]` |
| `execucao.evt.enfileirada` | Execução | `execucao_id`, `posicao_na_fila` |
| `execucao.evt.rejeitada` | Execução | `motivo` (ex.: `capacidade_esgotada`) |
| `execucao.cmd.cancelar` | OS | `motivo` |
| `execucao.evt.cancelada` | Execução | `execucao_id` |
| `execucao.evt.status-alterado` | Execução | `execucao_id`, `status_anterior`, `status`, `ocorrido_em` |
| `execucao.evt.finalizada` | Execução | `execucao_id`, `finalizada_em` |

Status da execução: `na_fila → em_diagnostico → em_reparo → finalizada`, e
`cancelada` a partir de `na_fila`.
