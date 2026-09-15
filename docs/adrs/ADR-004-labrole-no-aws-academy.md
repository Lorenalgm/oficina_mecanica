# ADR-004 — Reaproveitamento da LabRole no AWS Academy

- **Status:** aceito
- **Data:** 2026-09-15

## Contexto

O ambiente de nuvem é o **AWS Academy Learner Lab**
([RFC-001](../rfcs/RFC-001-escolha-da-nuvem.md)). Ele nega `iam:CreateRole`,
`iam:AttachRolePolicy` e `iam:PutRolePolicy`, e fornece uma role
pré-configurada chamada **`LabRole`**, com permissões amplas sobre os serviços
liberados.

Três recursos do projeto precisam de role de execução: as Lambdas de
autenticação, o control plane do EKS e o managed node group.

## Decisão

Todos os três referenciam a `LabRole` por `data source`, em vez de criar roles
próprias:

```hcl
data "aws_iam_role" "lambda" {
  name = var.lambda_role_name   # default: "LabRole"
}
```

No módulo do EKS, isso significa `create_iam_role = false` e `iam_role_arn`
apontando para a `LabRole`, tanto no cluster quanto no node group. Também ficam
desligadas a chave KMS de criptografia de secrets e o log group do control plane
(`create_kms_key = false`, `cluster_enabled_log_types = []`), pela mesma
restrição de IAM — e, de quebra, isso evita gastar orçamento com CloudWatch.

O nome da role é **variável, não literal**. Fora do Learner Lab, basta apontar
`lambda_role_name` / `iam_role_name` para roles dedicadas.

## Justificativa

Não havia escolha técnica: criar a role falha com `AccessDenied`. O que se
decidiu foi **como** acomodar a restrição sem contaminar o desenho — daí o
`data source` parametrizado, em vez de um ARN fixo espalhado pelos arquivos.

## Consequências

**Isto viola o princípio do mínimo privilégio.** A `LabRole` tem permissões muito
além do que uma Lambda que lê um segredo e consulta o banco precisaria. Numa
conta real, o correto seria:

- uma role por Lambda, com `AWSLambdaVPCAccessExecutionRole` e uma policy inline
  de `secretsmanager:GetSecretValue` restrita ao ARN do segredo;
- `AmazonEKSClusterPolicy` para o control plane;
- `AmazonEKSWorkerNodePolicy`, `AmazonEKS_CNI_Policy` e
  `AmazonEC2ContainerRegistryReadOnly` para os nodes.

Essa configuração chegou a existir em `oficina-auth-lambda/terraform/iam.tf` e
foi substituída quando a restrição do Learner Lab ficou conhecida — o histórico
do git preserva a versão correta.

**Outras consequências.**

- Um `terraform destroy` **não** apaga a `LabRole`: ela é do ambiente, não do
  state. É o comportamento desejado.
- A separação entre o que cada componente pode fazer deixa de ser garantida pela
  AWS e passa a depender do código. Aceito para escopo acadêmico.
- As credenciais do Learner Lab expiram a cada sessão de 4 horas e incluem
  `aws_session_token`; os workflows de CD enviam os três valores.
