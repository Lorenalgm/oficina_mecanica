# Plano da Fase 4 — Microsserviços e Saga

Plano de trabalho da Fase 4 do Tech Challenge (13SOAT). Serve de guia durante
a implementação e de base para os READMEs, os ADRs e o PDF de entrega.
Contrato das mensagens: [contrato-mensagens.md](contrato-mensagens.md).

## Decisões

| Tema | Decisão |
|---|---|
| Divisão | 3 microsserviços por área de negócio: OS, Billing, Execução |
| Linguagem | Laravel 11 / PHP 8.4 nos três, com a mesma arquitetura hexagonal (`src/Domain`, `src/Application`, `src/Infrastructure`) |
| Saga | **Orquestrada**, com o orquestrador dentro do OS Service |
| Mensageria | RabbitMQ no cluster, com outbox transacional, consumo idempotente, retentativa e DLQ |
| Banco do OS | PostgreSQL (RDS atual do `oficina-infra-db`) |
| Banco do Billing | PostgreSQL em **instância RDS própria** (`db.t3.micro`), via Terraform do próprio repositório, na VPC existente |
| Banco da Execução | **MongoDB** (StatefulSet no cluster; local e CI com `mongo`) |
| Pagamento | Mercado Pago (Checkout Pro, sandbox) atrás de uma porta `GatewayPagamento`; `FakeGateway` como padrão até haver credenciais |
| Síncrono (REST) | Só nas bordas: rotas públicas de cada serviço, webhook do Mercado Pago e rota interna `cliente por documento` usada pela Lambda |
| BDD | Behat (Gherkin em português) com o fluxo completo da Saga no OS Service |
| Qualidade | PHPUnit com cobertura ≥ 80% barrando o CI + SonarCloud com quality gate |
| Observabilidade | New Relic da Fase 3; `correlation_id` e `saga_id` em mensagens e logs; painel NRQL da Saga |

## Serviços

| Serviço | Repositório | Responsabilidade | Banco |
|---|---|---|---|
| OS Service | `oficina_mecanica` (atual) | Clientes, veículos, serviços, insumos e estoque; abertura, status e histórico da OS; consulta pública; orquestração da Saga | PostgreSQL |
| Billing Service | `oficina-billing-service` | Orçamento e aprovação/recusa com token; cobrança, webhook e estorno no Mercado Pago | PostgreSQL |
| Execução Service | `oficina-execucao-service` | Fila de execução por filial, diagnóstico, reparo, finalização e linha do tempo | MongoDB |

A Lambda de autenticação (`oficina-auth-lambda`) segue como borda. Hoje ela
faz `SELECT` direto na tabela `clientes` do banco da OS, o que fere a regra
"nenhum serviço acessa o banco de outro". Ela passa a chamar
`GET /internal/clientes/por-documento/{doc}` no OS Service.

## Saga da OS

Começa quando a OS já tem serviços e alguém pede o orçamento
(`POST /api/os/{id}/orcamento` → **202** com `saga_id`).

| # | Passo | Quem executa | Comando → resposta | Compensação |
|---|---|---|---|---|
| 1 | Solicitar orçamento | OS (local) | — | OS → *Cancelada* |
| 2 | Gerar orçamento | Billing | `gerar-orcamento` → `orcamento-gerado` \| `orcamento-falhou` | `cancelar-orcamento` |
| 3 | Aprovação do cliente | Billing (rota pública com token) | → `orcamento-aprovado` \| `orcamento-recusado` | — |
| 4 | Baixa de estoque | OS (local) | — | Devolver estoque |
| 5 | Pagamento | Billing + Mercado Pago | `cobrar` → `cobranca-criada`, depois `pagamento-confirmado` \| `pagamento-recusado` | `estornar` → `pagamento-estornado` |
| 6 | Enfileirar execução | Execução | `enfileirar` → `enfileirada` \| `rejeitada` | `cancelar` |

Depois do passo 6 a Saga está **concluída**. Os eventos `status-alterado` e
`finalizada` da Execução só atualizam o status da OS (o carro já está na
oficina, não há o que compensar). *Entregue* continua manual.

### Falhas e compensações (ordem reversa)

| Falha | Como provocar na demo | Compensações |
|---|---|---|
| Orçamento falhou | Serviço inativo ou item com valor zero | 1 |
| Cliente recusou | `POST /api/orcamentos/{id}/recusar` | 2, 1 |
| Estoque insuficiente | Insumo com estoque menor que o pedido | 2, 1 |
| Pagamento recusado | `FakeGateway` em modo recusa ou cartão de teste recusado no sandbox | 4, 2, 1 |
| Fila cheia na filial | `EXECUCAO_CAPACIDADE_POR_FILIAL=1` e duas OS seguidas | 5, 4, 2, 1 |

Se uma compensação falhar, ela é reenviada com a mesma política de
retentativa. Esgotadas as tentativas, a Saga vai para `compensacao_falhou`,
com log `error` e alerta no New Relic para ação manual.

### Estados da Saga e status da OS

| Estado da Saga | Status da OS |
|---|---|
| `aguardando_orcamento` | Em diagnóstico |
| `aguardando_aprovacao` | Aguardando aprovação |
| `aguardando_pagamento` | **Aguardando pagamento** (novo) |
| `aguardando_execucao`, `concluida` | Em execução |
| `compensando` | mantém o status atual |
| `compensada` | **Cancelada** (novo) |
| `compensacao_falhou` | mantém o status atual e aparece no painel |

A listagem de OS passa a ter a prioridade: Em execução → Aguardando pagamento
→ Aguardando aprovação → Em diagnóstico → Recebida. *Cancelada* sai da
listagem, assim como *Finalizada* e *Entregue*.

A OS ganha o campo `filial` (padrão `MATRIZ`), usado pela Execução para a
fila e a capacidade por filial.

## Etapas

Cada etapa vira um ou mais PRs na `main` do repositório indicado, com CI verde.

### 1. Preparação
- [x] Atualizar `oficina_mecanica` com a `origin/main`
- [x] Plano e contrato de mensagens (`docs/fase4/`)
- [ ] Criar `oficina-billing-service` e `oficina-execucao-service` no GitHub
- [ ] Projetos no SonarCloud e secrets dos repositórios (`SONAR_TOKEN`, `AWS_*`, `APP_KEY`)
- [ ] Conta de desenvolvedor e credenciais de teste do Mercado Pago (não bloqueia)

> **AWS Academy:** a conta do Learner Lab é zerada a cada challenge. O
> desenvolvimento roda no **kind**; a AWS só é recriada perto do deploy (etapa
> 8), nesta ordem: bucket S3 + tabela DynamoDB do estado do Terraform (manual,
> `us-east-1`) → secrets dos repositórios (`AWS_*`, `TFSTATE_*`) → pipelines do
> `oficina-infra-db`, do `oficina-infra-k8s` e da Lambda → serviços.

### 2. Plataforma (`oficina-infra-k8s`)
- [x] RabbitMQ 4.1 (StatefulSet com volume) com plugins de management e Prometheus
- [x] Job com a topologia do contrato (vhost, exchanges, filas, retry, DLQ, um usuário por serviço)
- [x] MongoDB 8 (StatefulSet de 1 réplica com volume) e usuário `execucao`
- [x] Aplicados pelo Terraform do kind (`kind/plataforma.tf`)
- [ ] EKS: addon `aws-ebs-csi-driver` e aplicação de `plataforma/` (quando a AWS for recriada)
- [ ] Credenciais no Secrets Manager: `oficina/rabbitmq`, `oficina/execucao`
- [ ] New Relic coletando profundidade de fila e DLQ do RabbitMQ
- [ ] README com o diagrama atualizado

### 3. OS Service — mensageria
- [ ] `php-amqplib`, `config/mensageria.php`, variáveis `RABBITMQ_*`
- [ ] Porta `MessagePublisher` e value object `Envelope`
- [ ] Tabelas `outbox` e `mensagens_processadas`; `OutboxPublisher`
- [ ] `php artisan outbox:publicar` (loop, `FOR UPDATE SKIP LOCKED`, confirms)
- [ ] `php artisan mensageria:consumir` (roteamento por `type`, idempotência, retry, DLQ, `correlation_id`)
- [ ] Broker fake em memória para os testes

### 4. OS Service — Saga
- [ ] Status *Aguardando pagamento* e *Cancelada*; `PrioridadeStatus` atualizado
- [ ] Campo `filial` na OS
- [ ] Tabelas `sagas` e `saga_passos`; entidade `SagaOS` com a máquina de estados
- [ ] `IniciarSagaOS` no `POST /api/os/{id}/orcamento` (202; 409 se já houver Saga ativa)
- [ ] Handlers de todos os eventos do contrato
- [ ] Compensações locais (devolver estoque, cancelar OS) e remotas (comandos)
- [ ] `compensacao_falhou` e comando `saga:reenviar {saga_id}`
- [ ] `GET /api/os/{id}/saga`
- [ ] Remover `GerarOrcamento`, `AprovarOrcamento`, `RecusarOrcamento` e as rotas de aprovar/recusar
- [ ] E-mail ao cliente com o link de aprovação e o link de pagamento
- [ ] Testes de feature: caminho feliz e cada falha da tabela

### 5. OS Service — BDD e rota interna
- [ ] Behat com `features/saga_os.feature` em português (caminho feliz + `Esquema do Cenário` das falhas)
- [ ] Middleware `ValidarTokenInterno` e `GET /internal/clientes/por-documento/{doc}`
- [ ] Ingress público sem `/internal`

### 6. Billing Service (`oficina-billing-service`)
- [ ] Esqueleto Laravel com o mesmo padrão do OS (middlewares, logs JSON, `/up`)
- [ ] Mensageria (mesma camada do OS)
- [ ] Domínio `Orcamento` (regras e token de uso único portados do `OSOrcamento`) e `Pagamento`
- [ ] Handlers `gerar-orcamento`, `cancelar-orcamento`, `cobrar`, `estornar`
- [ ] Rotas públicas de aprovar/recusar orçamento, consulta de pagamento, simulação e webhook
- [ ] `MercadoPagoGateway` (preferência, consulta, estorno, validação de `x-signature`) e `FakeGateway`
- [ ] Terraform da instância RDS própria e do segredo `oficina/billing`
- [ ] Testes ≥ 80%, Sonar, `openapi.yaml`, Docker, `k8s/`, CI/CD, README

### 7. Execução Service (`oficina-execucao-service`)
- [ ] Esqueleto Laravel e mensageria
- [ ] `mongodb/laravel-mongodb` (extensão `pecl mongodb`)
- [ ] Domínio `Execucao` (transições, linha do tempo, capacidade por filial)
- [ ] Repositório Mongo com outbox e `processadas` no próprio documento (atômico sem transação multi-documento)
- [ ] Handlers `enfileirar` e `cancelar`; rotas de fila, detalhe e avanço de status
- [ ] Testes ≥ 80% (integração com `mongo` no CI), Sonar, `openapi.yaml`, Docker, `k8s/`, CI/CD, README

### 8. OS Service — CI/CD e infra
- [ ] `ci.yml` com cobertura mínima, Behat e quality gate
- [ ] Deployment do worker (consumidor + outbox) e secrets novos no `cd.yml`
- [ ] `docker-compose` com os 3 serviços, RabbitMQ, Postgres e Mongo para a demo local
- [ ] `openapi.yaml` atualizado

### 9. Autenticação (`oficina-auth-lambda`)
- [ ] `authenticate` chama a rota interna do OS e remove o `pg`
- [ ] Rotas públicas no Gateway: aprovar/recusar orçamento e webhook do Mercado Pago

### 10. Proteção, observabilidade e evidências
- [ ] `main` protegida nos repositórios (PR obrigatório + check do CI)
- [ ] Painel NRQL "Saga da OS"; alertas de DLQ e de `compensacao_falhou`
- [ ] Print da cobertura (Sonar) no README de cada serviço

### 11. Documentação e entrega
- [ ] ADRs: divisão em microsserviços, Saga orquestrada, RabbitMQ + outbox, MongoDB na Execução, Mercado Pago
- [ ] README "Arquitetura da Fase 4" (diagrama geral, serviços e bancos, Saga e justificativa, links)
- [ ] `docs/arquitetura/saga.md` (máquina de estados e diagramas de sequência)
- [ ] Roteiro do vídeo (≤ 15 min): fluxo completo, falha com compensação, PR → CI → deploy, rastreamento no New Relic
- [ ] PDF: participante, links, vídeo, diagrama, estratégia da Saga, justificativas

## Checklist do enunciado

| Requisito | Onde é atendido |
|---|---|
| ≥ 3 microsserviços, cada um com repositório, infra e banco | Etapas 4, 6 e 7 |
| Pelo menos um SQL e um NoSQL | Postgres (OS, Billing) e MongoDB (Execução) |
| REST síncrono + mensageria assíncrona | Bordas REST; RabbitMQ entre os serviços |
| Nenhum serviço acessa o banco de outro | Snapshots nas mensagens; Lambda via rota interna (etapa 9) |
| Saga com rollback/compensação, justificada no README | Etapas 4 e 11 |
| Testes unitários em todos os serviços | Etapas 3 a 7 |
| Um fluxo completo com BDD | Behat (etapa 5) |
| Cobertura ≥ 80% por serviço | CI barra abaixo de 80% |
| SonarQube ou similar no CI | SonarCloud com quality gate |
| CI/CD independente com build, testes, qualidade e deploy em Kubernetes | Etapas 6, 7 e 8 |
| `main` protegida com PR e checagens | Etapa 10 |
| Monitoramento da Fase 3 | New Relic + painel da Saga (etapa 10) |
| Dockerfile, manifestos, Swagger, evidência de cobertura e docs por repositório | Etapas 6, 7, 8 e 10 |
| Vídeo ≤ 15 min e PDF | Etapa 11 |
