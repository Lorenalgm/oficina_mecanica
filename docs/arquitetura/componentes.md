# Diagrama de componentes

Visão geral da Fase 4 na AWS. Leia o **caminho da requisição pelos números
(1 → 4)**. As setas pontilhadas são apoio: segredos e monitoramento.

```mermaid
flowchart LR
    user(["👤 Cliente / atendente / mecânico"])

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
            lb["🌐 Load Balancer + Ingress<br/>roteia por caminho"]
            os["⚙️ OS Service<br/>cadastros, estoque, OS · orquestra a Saga"]
            billing["💳 Billing Service<br/>orçamento e pagamento"]
            exec["🔧 Execução Service<br/>fila e etapas"]
            mq[["🐇 RabbitMQ"]]
            pgb[("🐘 PostgreSQL<br/>Billing")]
            mongo[("🍃 MongoDB<br/>Execução")]
            agent["📡 Agente New Relic"]
        end

        db[("🐘 PostgreSQL (RDS)<br/>OS Service")]
        sm["🔑 Secrets Manager<br/>senha do banco e chaves"]
    end

    nr["📊 New Relic<br/>painéis e alertas"]

    user ==>|"1 · login com CPF"| gw
    gw ==> auth
    auth -->|"rota interna do OS"| lb
    user ==>|"2 · chamada com token"| gw
    gw ==>|"3 · valida o token"| authz
    gw ==>|"4 · encaminha"| lb
    lb ==> os & billing & exec
    os --> db
    billing --> pgb
    exec --> mongo
    os <-->|comandos e eventos| mq
    billing <--> mq
    exec <--> mq

    auth -.-> sm
    authz -.-> sm
    eks -.->|logs| agent
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
    class lb,os,billing,exec,mq app;
    class db,pgb,mongo data;
    class agent,nr obs;
```

**Legenda:** ⬜ usuários · 🟦 aplicação e mensageria · 🟪 autenticação e segredos · 🟩 bancos · 🟨 monitoramento

## Quem faz o quê

| Componente | Função | Repositório |
|---|---|---|
| API Gateway | Entrada única; limita a taxa de chamadas | `oficina-auth-lambda` |
| Lambda `authenticate` | Valida o CPF, consulta o cliente na rota interna do OS e gera o token (JWT) | `oficina-auth-lambda` |
| Lambda `authorizer` | Bloqueia chamadas sem token válido antes de chegarem ao cluster | `oficina-auth-lambda` |
| OS Service | Cadastros, estoque, OS e histórico; orquestra a Saga | `oficina_mecanica` |
| Billing Service | Orçamento, aprovação, cobrança e estorno | `oficina-billing-service` |
| Execução Service | Fila por filial e etapas da execução | `oficina-execucao-service` |
| RabbitMQ | Comandos e eventos entre os serviços, com retentativa e DLQ | `oficina-infra-k8s` |
| PostgreSQL do Billing | Banco exclusivo do Billing, dentro do cluster | `oficina-billing-service` |
| MongoDB | Banco exclusivo da Execução | `oficina-infra-k8s` |
| EKS + Ingress | Roda os serviços e ajusta o número de pods | `oficina-infra-k8s` |
| Agente New Relic | Envia logs e métricas para os painéis | `oficina-infra-k8s` |
| RDS PostgreSQL | Banco exclusivo do OS Service | `oficina-infra-db` |
| Secrets Manager | Senha do banco, chave do token e token interno | `oficina-infra-db` |

Nenhum serviço acessa o banco de outro: o que um serviço precisa do outro
chega no payload das mensagens ([contrato](../fase4/contrato-mensagens.md)).

## Por que o token é conferido duas vezes

1. **No Gateway**, para barrar chamadas inválidas antes de gastar recursos do
   cluster.
2. **Nos serviços**, porque o cluster também pode ser acessado sem passar pelo
   Gateway (por dentro da rede ou rodando local no kind).

Detalhes no [ADR-001](../adrs/ADR-001-api-gateway-lambda-authorizer.md).
