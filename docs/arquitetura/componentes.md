# Diagrama de componentes

Visão geral da Fase 3 na AWS. Leia o **caminho da requisição pelos números
(1 → 4)**. As setas pontilhadas são apoio: segredos e monitoramento.

```mermaid
flowchart LR
    user(["👤 Cliente<br/>Postman / navegador"])

    subgraph aws["☁️ AWS — us-east-1"]
        direction LR
        gw["🚪 API Gateway<br/>porta única de entrada"]

        subgraph lambdas["🔐 Autenticação (Lambda)"]
            direction TB
            auth["🪪 authenticate<br/>recebe o CPF e devolve o token"]
            authz["🛡️ authorizer<br/>confere o token a cada chamada"]
        end

        subgraph eks["☸️ Cluster Kubernetes (EKS)"]
            direction TB
            lb["🌐 Load Balancer + Ingress<br/>entrada do cluster"]
            api["⚙️ oficina-api<br/>regras da oficina · 2 a 10 pods"]
            agent["📡 Agente New Relic<br/>coleta logs e métricas"]
        end

        db[("🐘 PostgreSQL (RDS)<br/>dados da oficina")]
        sm["🔑 Secrets Manager<br/>senha do banco e chave do token"]
    end

    nr["📊 New Relic<br/>painéis e alertas"]

    user ==>|"1 · login com CPF"| gw
    gw ==> auth
    auth -->|busca o cliente| db
    user ==>|"2 · chamada com token"| gw
    gw ==>|"3 · valida o token"| authz
    gw ==>|"4 · encaminha"| lb
    lb ==> api
    api -->|lê e grava| db

    auth -.-> sm
    authz -.-> sm
    api -.->|logs| agent
    agent -.-> nr

    style aws fill:#f8fafc,stroke:#64748b,color:#0f172a
    style lambdas fill:#fdf4ff,stroke:#a21caf,color:#701a75
    style eks fill:#eff6ff,stroke:#1d4ed8,color:#1e3a8a
    classDef entry fill:#e2e8f0,stroke:#475569,color:#0f172a;
    classDef edge fill:#e0e7ff,stroke:#4f46e5,color:#312e81;
    classDef sec fill:#f5d0fe,stroke:#a21caf,color:#701a75;
    classDef app fill:#dbeafe,stroke:#2563eb,color:#1e3a8a;
    classDef data fill:#dcfce7,stroke:#15803d,color:#14532d;
    classDef obs fill:#fef08a,stroke:#a16207,color:#713f12;
    class user entry;
    class gw edge;
    class auth,authz,sm sec;
    class lb,api app;
    class db data;
    class agent,nr obs;
```

**Legenda:** ⬜ cliente · 🟦 aplicação · 🟪 autenticação e segredos · 🟩 banco · 🟨 monitoramento

## Quem faz o quê

| Componente | Função | Repositório |
|---|---|---|
| API Gateway | Entrada única; limita a taxa de chamadas | `oficina-auth-lambda` |
| Lambda `authenticate` | Valida o CPF, busca o cliente e gera o token (JWT) | `oficina-auth-lambda` |
| Lambda `authorizer` | Bloqueia chamadas sem token válido antes de chegarem ao cluster | `oficina-auth-lambda` |
| `oficina-api` | Regras da oficina; confere o token de novo | `oficina-api` |
| EKS + Ingress | Roda a API e ajusta o número de pods | `oficina-infra-k8s` |
| Agente New Relic | Envia logs e métricas para os painéis | `oficina-infra-k8s` |
| RDS PostgreSQL | Guarda os dados | `oficina-infra-db` |
| Secrets Manager | Guarda a senha do banco e a chave do token | `oficina-infra-db` |

## Por que o token é conferido duas vezes

1. **No Gateway**, para barrar chamadas inválidas antes de gastar recursos do
   cluster.
2. **Na API**, porque o cluster também pode ser acessado sem passar pelo
   Gateway (por dentro da rede ou rodando local no kind).

Detalhes no [ADR-001](../adrs/ADR-001-api-gateway-lambda-authorizer.md).
