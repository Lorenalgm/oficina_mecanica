# ADR-006 — Saga orquestrada no OS Service

- **Status:** aceito
- **Data:** 2026-11-16

## Contexto

Pedir o orçamento de uma OS dispara uma transação que passa pelos três
serviços: gerar orçamento (Billing), aprovação do cliente (Billing), baixa de
estoque (OS), pagamento (Billing) e entrada na fila de execução (Execução). Não
há transação distribuída; se um passo falha, os anteriores precisam ser
desfeitos. O enunciado exige uma Saga, orquestrada ou coreografada, com a
escolha justificada.

## Decisão

**Saga orquestrada**, com o orquestrador (`OrquestradorSagaOS`) dentro do OS
Service. O estado fica nas tabelas `sagas` e `saga_passos`, e a entidade
`SagaOS` concentra a máquina de estados. O orquestrador envia comandos
(`billing.cmd.*`, `execucao.cmd.*`) e reage aos eventos de resposta
(`billing.evt.*`, `execucao.evt.*`) recebidos na fila `os.saga.eventos`.

Detalhes do fluxo e das compensações: [saga.md](../arquitetura/saga.md).

## Justificativa

**Fluxo em um lugar só.** A ordem dos passos e das compensações está em
`SagaOS::falhar()`, e não espalhada pelos três serviços. Para saber em que ponto
uma OS parou, basta `GET /api/os/{id}/saga`.

**Compensação em ordem reversa é explícita.** A Saga guarda o que já foi feito
(`orcamentoId`, `estoqueBaixado`, pagamento confirmado) e monta a lista de
compensações a partir disso. Na coreografia, cada serviço precisaria inferir o
que desfazer a partir dos eventos dos outros.

**Billing e Execução não se conhecem.** Eles só respondem a comandos. Billing
não sabe que existe fila de execução, e Execução não sabe que existe pagamento.

**Falha de compensação tem dono.** Se o estorno falhar três vezes, a Saga vai
para `compensacao_falhou`, com log `error` e o comando `saga:reenviar` para
ação manual.

**Por que no OS Service.** A OS é a dona do processo e os passos locais (baixa
e devolução de estoque, cancelamento da OS) ficam na mesma transação do estado
da Saga. Um serviço só para orquestrar seria mais um deploy e mais um banco.

## Alternativas descartadas

**Coreografia.** Menos acoplamento a um ponto central, mas o fluxo fica
implícito nas assinaturas de eventos de cada serviço, e é mais difícil testar e
explicar as compensações. Com cinco passos e quatro tipos de compensação, o
custo de entender o fluxo pesou mais.

**Orquestrador como serviço separado.** Mais um repositório e banco sem
responsabilidade de negócio própria.

## Consequências

- O OS Service conhece os comandos e eventos dos outros dois serviços; o
  contrato fica em [contrato-mensagens.md](../fase4/contrato-mensagens.md).
- Eventos fora de ordem (atrasados, duplicados ou de Saga encerrada) são
  ignorados com log `warning`.
- O fluxo completo e as falhas estão cobertos em BDD
  (`features/saga_os.feature`).
