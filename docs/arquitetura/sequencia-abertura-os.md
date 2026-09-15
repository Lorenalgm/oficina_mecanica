# Diagrama de sequência — abertura de ordem de serviço

```mermaid
sequenceDiagram
    autonumber
    actor C as Cliente
    participant GW as API Gateway
    participant AZ as Lambda authorizer
    participant API as oficina-api
    participant UC as CriarOS (use case)
    participant DB as RDS PostgreSQL
    participant EV as Listeners
    participant NR as New Relic

    C->>GW: POST /api/os { veiculo_id, cliente_id, descricao_problema, servicos[] }
    GW->>AZ: valida o Bearer
    AZ-->>GW: isAuthorized: true
    GW->>API: POST /api/os (HTTP_PROXY)

    API->>API: CorrelationId gera/propaga o correlation_id
    API->>API: StoreOSRequest valida o payload
    API->>UC: executar(veiculoId, clienteId, descricao, servicos)

    rect rgb(240,240,240)
        Note over UC,DB: transação única
        UC->>DB: INSERT INTO os (status_atual_id = "Recebida")
        UC->>DB: INSERT INTO os_status (data_status = now())
        loop cada serviço informado
            UC->>DB: INSERT INTO os_servicos
        end
    end
    DB-->>UC: os criada

    UC->>EV: dispara evento de mudança de status
    EV->>NR: RegistrarMetricasOS emite os_status_changed
    EV-->>C: EnviarEmailStatusOS notifica por e-mail (falha não derruba a OS)

    UC-->>API: entidade OS
    API->>NR: Log os_created (alimenta o painel de volume diário)
    API->>API: LogRequest emite http_request com duration_ms
    API-->>GW: 201 + OSResource
    GW-->>C: 201
```

## Por que a telemetria está em dois listeners separados

`RegistrarMetricasOS` e `EnviarEmailStatusOS` escutam o mesmo evento, mas em
classes distintas. Se a telemetria vivesse dentro do listener de e-mail, uma
falha do provedor de e-mail levaria junto a métrica `os_status_changed` — e o
painel de tempo médio por status ficaria com buracos justamente nos momentos de
incidente, que é quando ele importa.

## Estados da OS

```mermaid
stateDiagram-v2
    [*] --> Recebida
    Recebida --> EmDiagnostico: técnico assume
    EmDiagnostico --> AguardandoAprovacao: orçamento gerado
    AguardandoAprovacao --> EmExecucao: cliente aprova (approval_token)
    AguardandoAprovacao --> Recusada: cliente recusa
    EmExecucao --> Finalizada: serviços concluídos
    Finalizada --> Entregue: veículo retirado
    Entregue --> [*]
    Recusada --> [*]
```

Cada transição grava uma linha em `os_status` com `data_status`. A diferença
entre linhas consecutivas é o `duracao_status_min` que o painel "tempo médio de
execução por status" consome.
