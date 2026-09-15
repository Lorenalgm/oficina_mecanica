# ADR-003 — Observabilidade por logs estruturados, sem agente APM

- **Status:** aceito
- **Data:** 2026-09-15

## Contexto

A Fase 3 exige monitorar latência das APIs, CPU e memória do cluster,
healthchecks, alertas de falha no processamento de OS e **logs estruturados em
JSON com correlação de requisições**, com dashboards no New Relic.

O caminho canônico seria instalar o **agente APM de PHP do New Relic**, que
instrumenta a aplicação automaticamente e entrega transações, traces e erros sem
tocar no código.

## O impedimento

A imagem de produção usa **FrankenPHP**, que embute o PHP no Caddy e é compilado
em modo **ZTS** (*Zend Thread Safety*) para servir requisições em goroutines. O
agente APM de PHP do New Relic é distribuído apenas para builds **NTS**
(*non-thread-safe*) e não carrega em ZTS. Não é configuração: é incompatibilidade
de build.

Trocar o FrankenPHP por php-fpm só para caber o agente significaria reescrever o
Dockerfile de produção, perder o *worker mode* e divergir do que a Fase 2
entregou — um custo alto para um meio, não para um fim.

## Decisão

**Logs estruturados em JSON, coletados pelo `newrelic-logging` do `nri-bundle`,
com as métricas derivadas por NRQL.**

A aplicação escreve uma linha JSON por requisição no stdout. O agente de logging
do New Relic, que roda como DaemonSet e não depende do runtime da aplicação,
coleta o stdout dos pods. Cada campo do JSON vira um atributo consultável.

Peças no código:

| Peça | Papel |
|---|---|
| `app/Http/Middleware/CorrelationId.php` | Lê `X-Request-Id` ou o segmento `Root=` do `X-Amzn-Trace-Id`; gera UUID se não houver; ecoa na resposta |
| `app/Support/CorrelationContext.php` | Guarda o id para o processor do Monolog, que é resolvido no boot — antes de o middleware rodar |
| `app/Logging/CorrelationProcessor.php` | Injeta `correlation_id`, `service` e `env` em todo registro |
| `app/Http/Middleware/LogRequest.php` | Emite `http_request` com `duration_ms`, `route`, `status` — **é daqui que sai a latência** |
| `bootstrap/app.php` (`withExceptions`) | Marca exceções de `Domain\Atendimento` como `os_processing_failure` — gancho do alerta exigido |
| `app/Listeners/RegistrarMetricasOS.php` | Emite `os_status_changed` com `duracao_status_min` |
| `OSController::store` | Emite `os_created`, que alimenta o painel de volume diário |

O que o `nri-bundle` entrega sem tocar na aplicação: CPU e memória por pod
(`K8sContainerSample`), eventos do cluster e reinícios de pod. O uptime vem de um
**Synthetics ping monitor** em `/up`.

## Justificativa

Além de contornar o impedimento, a abordagem tem valor próprio: o formato do log
é o mesmo no kind e no EKS, então os dashboards podem ser montados e validados
localmente, sem cluster na nuvem. Não há dependência de um agente proprietário
dentro do processo da aplicação, e os mesmos logs funcionariam com Datadog, Loki
ou CloudWatch trocando só o coletor.

## Consequências

**Positivas.** Zero acoplamento entre aplicação e fornecedor de observabilidade.
Funciona idêntico em kind e EKS. A correlação atravessa Gateway, ingress e
aplicação por um único id.

**Negativas.**

- *Sem tracing distribuído automático.* Não há waterfall de spans nem
  instrumentação de queries individuais. A correlação por `correlation_id`
  cobre o requisito do enunciado, mas não substitui um APM de verdade.
- *Latência medida no middleware, não no processo inteiro.* `duration_ms` conta
  do início ao fim do pipeline HTTP do Laravel; não inclui o tempo de rede nem o
  do Caddy. Para o propósito (comparar rotas e detectar regressão) é suficiente.
- *Volume de log é custo.* `global.lowDataMode = true` no `nri-bundle` e
  `LOG_LEVEL: info` mantêm o consumo dentro do plano gratuito.
- *Se o formato do log mudar, o dashboard quebra silenciosamente.* Por isso as
  consultas estão versionadas em
  [`docs/observabilidade/nrql.md`](../observabilidade/nrql.md) e há teste
  cobrindo o formato (`tests/Feature/ObservabilidadeTest.php`).
