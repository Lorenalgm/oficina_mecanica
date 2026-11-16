# ADR-008 — MongoDB no Execução Service

- **Status:** aceito
- **Data:** 2026-11-16

## Contexto

O enunciado pede pelo menos um banco SQL e um NoSQL. O Execução Service guarda
a fila por filial e, para cada OS, o veículo, os serviços, o status
(`na_fila → em_diagnostico → em_reparo → finalizada`) e a linha do tempo das
etapas.

## Decisão

**MongoDB 8** (StatefulSet de 1 réplica, provisionado pelo `oficina-infra-k8s`),
com uma coleção de execuções. Cada execução é um documento com:

- `_id` igual ao `saga_id` da OS;
- `linha_do_tempo` embutida;
- `outbox` embutida: os eventos a publicar ficam num array do próprio
  documento.

## Justificativa

**Por que documento.** A execução é lida e gravada inteira (status, serviços e
linha do tempo juntos), sem junções. O documento corresponde ao agregado.

**Idempotência sem tabela extra.** Como `_id = saga_id`, um `enfileirar`
repetido falha com chave duplicada (código 11000) e é tratado como já
processado.

**Outbox sem transação multi-documento.** O MongoDB garante atomicidade na
escrita de um documento. Com a outbox dentro do documento, o efeito e o evento
são gravados na mesma operação (`insertOne` ou `updateOne` com `$push`). Isso
dispensa replica set e transações, e o MongoDB pode rodar com uma réplica só.

## Alternativas descartadas

**PostgreSQL também na Execução.** Funcionaria, mas não atenderia ao requisito
de um banco NoSQL.

**DynamoDB.** Não roda no kind e acoplaria o serviço à AWS.

**Outbox em coleção separada com transação.** Exigiria replica set só para
isso.

## Consequências

- O publicador busca documentos com `outbox.publicado_em` nulo (há índice) e
  marca cada item como publicado.
- Uma OS tem no máximo uma execução por Saga.
- Consultas que cruzem execuções (como capacidade por filial) são feitas por
  filtro na coleção, não por junção.
