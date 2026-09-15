# Diagrama de componentes

```mermaid
flowchart TB
  Cliente(["Cliente / Postman"])

  subgraph AWS["AWS — us-east-1"]
    GW["API Gateway HTTP API\nthrottling 50 rps / burst 100\naccess log JSON"]

    subgraph Lambdas["AWS Lambda — Node.js 22"]
      AUTH["authenticate\nPOST /auth\nvalida CPF, consulta cliente, assina JWT"]
      AUTHZ["authorizer\nLambda Authorizer REQUEST\nverifica assinatura e exp"]
    end

    subgraph EKS["Amazon EKS — oficina-eks"]
      NLB["ingress-nginx (NLB)"]
      API["oficina-api\nLaravel 11 / FrankenPHP\nHPA 2..6 replicas"]
      NR["nri-bundle\ninfra + logging + kube-events"]
    end

    RDS[("RDS PostgreSQL 16\ndb.t3.micro, subnet privada")]
    SM["Secrets Manager\noficina/app"]
  end

  NRC["New Relic\nDashboards, Alertas, Synthetics"]

  Cliente -->|"POST /auth {cpf}"| GW
  Cliente -->|"ANY /api/* + Bearer"| GW
  GW --> AUTH
  GW -->|autoriza| AUTHZ
  GW -->|"HTTP_PROXY (autorizado)"| NLB
  NLB --> API

  AUTH --> RDS
  AUTH --> SM
  AUTHZ --> SM
  API --> RDS
  API -.->|"Secret do k8s"| SM

  API -->|"stdout JSON"| NR
  NR --> NRC
  GW -.->|Synthetics ping /up| NRC
```

## Responsabilidades

| Componente | Responsabilidade | Repositório |
|---|---|---|
| API Gateway | Porta única de entrada, throttling, log de acesso, delega autorização | `oficina-auth-lambda` |
| `authenticate` | Valida os dígitos do CPF, verifica se o cliente existe e emite o JWT HS256 | `oficina-auth-lambda` |
| `authorizer` | Valida o JWT em cada chamada a `/api/*` antes de o Gateway encaminhar | `oficina-auth-lambda` |
| `oficina-api` | Regras de negócio da oficina; revalida o JWT por conta própria | `oficina-api` |
| RDS PostgreSQL | Estado do domínio | `oficina-infra-db` |
| Secrets Manager | Credenciais do banco e segredo HS256 compartilhado | `oficina-infra-db` |
| EKS + ingress | Execução e escalabilidade da aplicação | `oficina-infra-k8s` |
| nri-bundle | Coleta logs, métricas de pod e eventos do cluster | `oficina-infra-k8s` |

## O JWT é validado duas vezes, de propósito

O Authorizer do Gateway rejeita o tráfego não autenticado na borda, antes de
consumir capacidade do cluster. O middleware `ValidarJwt` na aplicação repete a
verificação porque (a) o cluster é alcançável por dentro da VPC sem passar pelo
Gateway e (b) permite rodar a API inteira no kind, sem AWS, durante o
desenvolvimento. É defesa em profundidade, não redundância acidental —
detalhado no [ADR-001](../adrs/ADR-001-api-gateway-lambda-authorizer.md).
