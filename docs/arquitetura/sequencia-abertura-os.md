# Abertura de ordem de serviço

## Fluxo da requisição

```mermaid
sequenceDiagram
    autonumber
    actor C as 👤 Cliente
    participant GW as 🚪 API Gateway
    participant AZ as 🛡️ Lambda authorizer
    participant API as ⚙️ oficina-api
    participant DB as 🐘 Banco (RDS)
    participant NR as 📊 New Relic

    C->>GW: POST /api/os (cliente, veículo, problema, serviços)
    GW->>AZ: valida o token
    AZ-->>GW: token válido
    GW->>API: encaminha a chamada

    rect rgb(239, 246, 255)
        Note over API: validação
        API->>API: confere os campos obrigatórios
        API->>DB: os serviços e peças informados existem?
        DB-->>API: sim
    end

    rect rgb(220, 252, 231)
        Note over API,DB: gravação
        API->>DB: cria a OS com status "Recebida"
        API->>DB: registra "Recebida" no histórico
        loop para cada serviço
            API->>DB: adiciona o serviço e suas peças
        end
    end

    API-)NR: log "OS criada" (painel de volume diário)
    API-->>C: 201 · OS criada com o id
```

Se algum serviço ou peça não existir, a API responde com erro **antes** de
gravar, para não sobrar OS pela metade.

## Ciclo de vida da OS

Depois da abertura, quem muda o status é a **Saga** ([saga.md](saga.md)). A
troca manual (`PATCH /api/os/{id}/status`) fica bloqueada com **409** enquanto
a Saga da OS estiver ativa.

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Recebida
    Recebida --> EmDiagnostico: orçamento solicitado (Saga começa)
    EmDiagnostico --> AguardandoAprovacao: Billing gerou o orçamento
    AguardandoAprovacao --> AguardandoPagamento: cliente aprova · estoque baixado
    AguardandoPagamento --> EmExecucao: pagamento confirmado
    EmExecucao --> Finalizada: Execução finaliza
    Finalizada --> Entregue: cliente retira o carro
    Entregue --> [*]
    AguardandoAprovacao --> Cancelada: recusa
    AguardandoPagamento --> Cancelada: pagamento recusado (compensação)

    EmDiagnostico: Em diagnóstico
    AguardandoAprovacao: Aguardando aprovação
    AguardandoPagamento: Aguardando pagamento
    EmExecucao: Em execução

    classDef inicio fill:#e0e7ff,stroke:#4f46e5,color:#312e81
    classDef espera fill:#fef08a,stroke:#a16207,color:#713f12
    classDef trabalho fill:#dbeafe,stroke:#2563eb,color:#1e3a8a
    classDef fim fill:#dcfce7,stroke:#15803d,color:#14532d
    classDef erro fill:#fee2e2,stroke:#b91c1c,color:#7f1d1d
    class Recebida inicio
    class AguardandoAprovacao,AguardandoPagamento espera
    class EmDiagnostico,EmExecucao trabalho
    class Finalizada,Entregue fim
    class Cancelada erro
```

- **Cada mudança de status** fica registrada na tabela `os_status` com a data.
  A diferença entre dois registros é o tempo gasto em cada etapa, usado no painel
  "tempo médio por status".
- **Aprovar o orçamento** (no Billing) faz a Saga baixar o estoque das peças.
  Se o pagamento for recusado, a Saga devolve o estoque, cancela o orçamento e
  cancela a OS.
- **Finalizada** chega pelo evento `execucao.evt.finalizada` do Execução
  Service. **Entregue** continua sendo marcado à mão, depois da Saga.
- **Mudanças de status** disparam um e-mail ao cliente e uma métrica no New
  Relic, em partes separadas do código: se o e-mail falhar, a métrica continua
  sendo registrada.
