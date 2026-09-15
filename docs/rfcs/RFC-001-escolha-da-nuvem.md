# RFC-001 — Escolha do provedor de nuvem

- **Status:** aceito
- **Data:** 2026-09-15
- **Autora:** Lorena Montes

## Contexto

A Fase 3 exige API Gateway, função serverless, banco gerenciado e cluster
Kubernetes, todos provisionados por Terraform e implantados por CI/CD. A Fase 2
rodava inteiramente em kind local, sem nuvem. É preciso escolher um provedor.

Restrição decisiva: o ambiente disponível é o **AWS Academy Learner Lab**, com
orçamento de **USD 50** e crédito não renovável — estourar o limite apaga o
ambiente e todo o trabalho. A tentativa de abrir uma conta AWS pessoal no plano
gratuito foi recusada: os dados já constavam de uma conta anterior, e a conta
seria criada em plano pago sem os USD 200 de crédito.

## Alternativas consideradas

| Critério | AWS (Learner Lab) | GCP | Azure |
|---|---|---|---|
| Custo efetivo | zero (crédito do curso) | USD 300 por 90 dias, exige conta nova | USD 200 por 30 dias, exige conta nova |
| API Gateway gerenciado | API Gateway HTTP API | API Gateway / Cloud Endpoints | API Management (caro no tier básico) |
| Serverless | Lambda, integração nativa com o Gateway | Cloud Functions / Cloud Run | Functions |
| Kubernetes gerenciado | EKS | GKE (control plane gratuito na zona) | AKS (control plane gratuito) |
| Banco gerenciado | RDS PostgreSQL | Cloud SQL | Database for PostgreSQL |
| Familiaridade da equipe | alta | média | baixa |
| Material do curso | todo em AWS | — | — |

## Decisão

**AWS**, pelo AWS Academy Learner Lab.

O fator determinante é que já existe acesso concedido e sem custo. GCP e Azure
ofereciam crédito maior e, no caso do GKE e do AKS, control plane gratuito — mas
ambos exigiriam abrir conta nova com cartão de crédito, e a conta AWS pessoal já
havia sido negada por esse mesmo motivo (dados previamente cadastrados). Somando
a isso o alinhamento com o material do curso e a familiaridade da equipe, o
ganho técnico de GKE/AKS não compensa o risco de ficar sem ambiente.

## Consequências

**Positivas.** Integração nativa entre API Gateway HTTP API, Lambda Authorizer e
RDS, sem cola própria. Terraform com provider AWS maduro e o módulo oficial de
EKS. Custo zero.

**Negativas, e como são tratadas.**

- *Não é possível criar IAM roles.* Lambdas, control plane e nodes reaproveitam
  a `LabRole` pré-existente. Registrado no
  [ADR-004](../adrs/ADR-004-labrole-no-aws-academy.md).
- *Credenciais expiram a cada sessão de 4 horas* e incluem `aws_session_token`.
  Os secrets do GitHub Actions precisam ser atualizados antes de cada deploy.
- *EC2 é encerrado ao fim da sessão, mas NLB e RDS não.* O alvo `make teardown`
  em `oficina-infra-k8s` remove o ingress (e com ele o NLB) antes de destruir o
  cluster; o RDS é pausado com `aws rds stop-db-instance`.
- *O EKS cobra USD 0,10/h pelo control plane, entre sessões inclusive.* O
  cluster só sobe na janela de demonstração; o desenvolvimento diário continua
  em kind, com os mesmos manifestos.
- *Orçamento de USD 50 é um teto rígido.* Configurar alerta de AWS Budget em
  USD 20 e conferir o consumo ao início de cada sessão.

## Referências

- [ADR-004 — LabRole no AWS Academy](../adrs/ADR-004-labrole-no-aws-academy.md)
- [RFC-002 — Banco de dados](RFC-002-banco-de-dados.md)
