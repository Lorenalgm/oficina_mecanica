# Observabilidade — consultas, dashboards e alertas

As consultas abaixo são a fonte de verdade dos painéis e alertas criados na UI
do New Relic. Estão versionadas aqui porque dependem do **formato dos logs** da
aplicação: se um campo mudar de nome, o painel para de retornar dados sem erro
aparente. Ver [ADR-003](../adrs/ADR-003-observabilidade-via-logs-estruturados.md).

## Como os dados chegam

A aplicação escreve **uma linha JSON por evento** no stdout. O `newrelic-logging`
do `nri-bundle` (DaemonSet, instalado pelo Terraform em `oficina-infra-k8s`)
coleta esse stdout e cada chave do JSON vira um atributo do tipo de evento `Log`.
CPU, memória e reinícios de pod vêm do `newrelic-infrastructure` como
`K8sContainerSample`.

## Eventos emitidos pela aplicação

| `event_type` | Onde nasce | Campos próprios |
|---|---|---|
| `http_request` | `App\Http\Middleware\LogRequest` | `method`, `route`, `path`, `status`, `duration_ms`, `cliente_id`, `ip` |
| `os_created` | `OSController::store` | `os_id`, `cliente_id`, `veiculo_id` |
| `os_status_changed` | `App\Listeners\RegistrarMetricasOS` | `os_id`, `status_anterior`, `status_novo`, `duracao_status_min` |
| `os_processing_failure` | `bootstrap/app.php` (`withExceptions`) | `exception_class`, `file`, `line` |
| `unhandled_exception` | `bootstrap/app.php` (`withExceptions`) | `exception_class`, `file`, `line` |

Todos carregam, via `CorrelationProcessor`: `correlation_id`, `service`, `env`,
`level`, `timestamp`.

> Durante o desenvolvimento no kind, troque `clusterName = 'oficina-eks'` por
> `'oficina-kind'`. Os demais filtros são idênticos nos dois ambientes.

---

## Dashboard "Oficina — Operação"

### 1. Latência das APIs (p50 / p95 / p99)

```sql
SELECT percentile(numeric(duration_ms), 50, 95, 99)
FROM Log
WHERE service = 'oficina-api' AND event_type = 'http_request'
FACET route
TIMESERIES SINCE 1 hour ago
```

`numeric()` é obrigatório: o coletor entrega os campos do JSON como string.

### 2. Volume diário de ordens de serviço

```sql
SELECT count(*)
FROM Log
WHERE service = 'oficina-api' AND event_type = 'os_created'
TIMESERIES 1 day SINCE 7 days ago
```

### 3. Tempo médio de execução por status

Atende ao requisito de acompanhar Diagnóstico, Execução e Finalização:

```sql
SELECT average(numeric(duracao_status_min))
FROM Log
WHERE service = 'oficina-api' AND event_type = 'os_status_changed'
FACET status_anterior
SINCE 1 day ago
```

### 4. Erros e falhas de integração

```sql
SELECT count(*)
FROM Log
WHERE service = 'oficina-api' AND level = 'ERROR'
FACET exception_class
TIMESERIES SINCE 6 hours ago
```

### 5. Taxa de erro HTTP por rota

```sql
SELECT percentage(count(*), WHERE numeric(status) >= 500) AS 'erro 5xx',
       percentage(count(*), WHERE numeric(status) = 401 OR numeric(status) = 403) AS 'nao autorizado'
FROM Log
WHERE service = 'oficina-api' AND event_type = 'http_request'
FACET route
SINCE 1 hour ago
```

### 6. CPU e memória dos pods

```sql
SELECT average(cpuUsedCores), average(memoryWorkingSetBytes)
FROM K8sContainerSample
WHERE clusterName = 'oficina-eks' AND containerName = 'oficina-api'
FACET podName
TIMESERIES SINCE 1 hour ago
```

### 7. Réplicas em execução (efeito do HPA)

```sql
SELECT uniqueCount(podName)
FROM K8sContainerSample
WHERE clusterName = 'oficina-eks' AND containerName = 'oficina-api'
TIMESERIES SINCE 1 hour ago
```

### 8. Uptime e healthcheck

Painel do **Synthetics** (ver abaixo), acompanhado de:

```sql
SELECT percentage(count(*), WHERE result = 'SUCCESS')
FROM SyntheticCheck
WHERE monitorName = 'oficina-api /up'
TIMESERIES SINCE 1 day ago
```

### 9. Rastreio de uma requisição ponta a ponta

Painel de tabela, para a demonstração ao vivo:

```sql
SELECT timestamp, event_type, route, status, duration_ms, message
FROM Log
WHERE correlation_id = '<cole o X-Request-Id da resposta>'
ORDER BY timestamp ASC
LIMIT 100
```

---

## Synthetics

Monitor do tipo **Ping**, nome `oficina-api /up`:

- URL: `https://<api-gateway>/api/up` (ou a URL do NLB durante o desenvolvimento)
- Frequência: 5 minutos
- Locations: `us-east-1` e `us-west-1` — duas para que uma falha de rede numa
  única região não gere alerta falso

---

## Política de alertas "Oficina — Crítico"

Canal de notificação: e-mail. As quatro condições cobrem os itens exigidos.

### A. Falha no processamento de OS

Atende diretamente a "alertas para falhas no processamento de ordens de serviço".

```sql
SELECT count(*)
FROM Log
WHERE service = 'oficina-api' AND event_type = 'os_processing_failure'
```

- Tipo: NRQL, janela de 5 minutos
- Crítico: acima de `0` por pelo menos 5 minutos
- Sinal perdido: não abrir incidente (ausência de falha é o esperado)

### B. Latência alta

```sql
SELECT percentile(numeric(duration_ms), 95)
FROM Log
WHERE service = 'oficina-api' AND event_type = 'http_request'
```

- Crítico: acima de `1000` (ms) por 5 minutos
- Aviso: acima de `500` por 5 minutos

### C. Healthcheck fora do ar

Condição do tipo **Synthetics**, sobre o monitor `oficina-api /up`: abrir
incidente quando falhar em **2 ou mais locations**, evitando alarme por
instabilidade de um único ponto.

### D. Pods reiniciando

```sql
SELECT max(restartCount)
FROM K8sContainerSample
WHERE clusterName = 'oficina-eks' AND containerName = 'oficina-api'
```

- Crítico: acima de `0` por 5 minutos

---

## Como validar

Com a aplicação rodando (kind ou EKS):

```bash
# 1. Os logs estão chegando?
#    SELECT * FROM Log WHERE service = 'oficina-api' SINCE 10 minutes ago

# 2. Gerar tráfego para os painéis de latência
k6 run load/script.js

# 3. Forçar um os_processing_failure e conferir o alerta A disparando
curl -i -X PATCH "$API/api/os/999999/status" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"status_id": 99}'

# 4. Rastrear uma requisição ponta a ponta
curl -i "$API/api/os" -H "Authorization: Bearer $TOKEN" | grep -i x-request-id
#    e colar o valor na consulta 9
```
