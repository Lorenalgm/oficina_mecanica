# ADR-007 — RabbitMQ com outbox e consumidor idempotente

- **Status:** aceito
- **Data:** 2026-11-16

## Contexto

A Saga troca comandos e eventos entre os três serviços. Gravar no banco e
publicar no broker são duas operações separadas: se o processo cair entre uma e
outra, ou o efeito acontece sem a mensagem, ou a mensagem sai sem o efeito. E
toda mensagem pode chegar mais de uma vez.

## Decisão

- **RabbitMQ 4.1** no cluster, provisionado pelo `oficina-infra-k8s`
  (`plataforma/`): exchange `topic` `oficina` e uma fila por consumidor
  (`billing.comandos`, `execucao.comandos`, `os.saga.eventos`). A routing key é
  o `type` da mensagem.
- **Outbox transacional:** a mensagem é gravada na mesma transação do efeito e
  um processo publicador (`outbox:publicar`) envia com *publisher confirms*.
- **Consumidor idempotente:** o `message_id` é gravado junto com o efeito
  (`mensagens_processadas`); se a mensagem se repetir, o consumidor dá ack sem
  repetir o efeito.
- **Retentativa e DLQ:** erro técnico republica no exchange `oficina.retry`
  (fila `<fila>.retry` com TTL de 5 s); na 3ª falha ou com mensagem inválida, o
  broker manda para `<fila>.dlq` via `oficina.dlx`.
- Um usuário do RabbitMQ por serviço, que só publica em `oficina` e
  `oficina.retry` e só lê a própria fila.

## Justificativa

**Por que RabbitMQ.** Roda no próprio cluster (kind e EKS) sem depender de
serviço gerenciado, que o AWS Academy limita. Tem roteamento por tópico,
dead-letter e confirmação de publicação nativos, que é o que a Saga precisa.

**Por que outbox.** Com ela, a garantia é "pelo menos uma vez" sem transação
distribuída. O publicador pode reenviar depois de uma queda.

**Por que idempotência no consumidor.** "Pelo menos uma vez" implica
duplicatas. Registrar o `message_id` na mesma transação do efeito torna o
reprocessamento inofensivo.

**Erro de negócio não é retentado.** Estoque insuficiente ou fila cheia viram
eventos de falha e a Saga decide o que fazer; retentar não mudaria o resultado.

## Alternativas descartadas

**Amazon SQS/SNS.** Não roda no kind, o que tiraria o ambiente local e o CI com
broker real.

**Kafka.** Mais pesado de operar e sem ganho para o volume de uma oficina.

**Publicar direto após o commit.** Perde mensagens em queda do processo.

## Consequências

- Cada serviço tem dois processos além da API: consumidor
  (`mensageria:consumir`) e publicador da outbox.
- A topologia é criada pelo `oficina-infra-k8s`; os serviços não declaram
  filas.
- Mensagens na DLQ exigem ação manual.
