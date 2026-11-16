# ADR-005 — Divisão em três microsserviços

- **Status:** aceito
- **Data:** 2026-11-16

## Contexto

A Fase 4 pede pelo menos três microsserviços, cada um com repositório,
infraestrutura e banco próprios, e proíbe um serviço de acessar o banco de
outro. Até a Fase 3 tudo vivia na `oficina-api`: cadastros, OS, orçamento,
estoque e execução.

## Decisão

Dividir por área de negócio em três serviços, todos em Laravel 11 / PHP 8.4
com a mesma arquitetura hexagonal (`src/Domain`, `src/Application`,
`src/Infrastructure`):

| Serviço | Repositório | Responsabilidade | Banco |
|---|---|---|---|
| OS Service | `oficina_mecanica` | Cadastros, OS, estoque e orquestração da Saga | PostgreSQL |
| Billing Service | `oficina-billing-service` | Orçamento com token de aprovação, cobrança e estorno | PostgreSQL |
| Execução Service | `oficina-execucao-service` | Fila por filial, etapas da execução e linha do tempo | MongoDB |

Entre os serviços, a comunicação é por mensagens no RabbitMQ. REST fica nas
bordas: rotas públicas de cada serviço e a rota interna
`GET /internal/clientes/por-documento/{doc}`, usada pela Lambda de
autenticação.

## Justificativa

**Por que esses cortes.** Orçamento/pagamento e execução na oficina são etapas
com ciclo de vida e dados próprios, que mudam por motivos diferentes. Cadastros,
OS e estoque ficam juntos porque a baixa e a devolução de estoque precisam ser
locais e transacionais com a OS.

**Por que estoque fica no OS.** Separar estoque em um quarto serviço traria mais
um passo remoto na Saga e mais uma compensação, sem ganho para o desafio.

**Por que a mesma stack nos três.** O código de mensageria (outbox, consumidor,
retentativa) é o mesmo padrão em todos, e uma pessoa só mantém o projeto.

## Alternativas descartadas

**Separar cadastros (clientes e veículos) em um serviço.** A Saga precisaria
consultar outro serviço ou manter cópias para montar o pedido de orçamento.

**Manter um monólito modular.** Não atende ao enunciado.

## Consequências

- Nenhum serviço consulta outro durante a Saga: as mensagens levam um snapshot
  do que o destino precisa (itens com preço, e-mail do cliente).
- A Lambda deixou de ler a tabela `clientes` e passou a chamar a rota interna
  do OS com `X-Internal-Token`.
- São três pipelines, três imagens e três conjuntos de manifestos Kubernetes.
