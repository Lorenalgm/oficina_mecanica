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

No EKS, o cluster e o node group apontam `role_arn` / `node_role_arn` para a
`LabRole`. Também ficam desligadas a chave KMS de criptografia de secrets e o log
group do control plane (`enabled_cluster_log_types = []`), pela mesma restrição
de IAM — e, de quebra, isso evita gastar orçamento com CloudWatch.

O nome da role é **variável, não literal**. Fora do Learner Lab, basta apontar
`lambda_role_name` / `iam_role_name` para roles dedicadas.

## O módulo comunitário do EKS é incompatível com o Learner Lab

O caminho natural seria `terraform-aws-modules/eks/aws`, e foi o que se tentou
primeiro. Ele **não funciona neste ambiente**, e a falha não é contornável por
configuração:

```
Error: unable to get role (voclabs): api error AccessDenied: not authorized to
perform: iam:GetRole on resource: role voclabs with an explicit deny in an
identity-based policy: arn:aws:iam::<conta>:policy/Pvoclabs2
  with module.eks.data.aws_iam_session_context.current[0]
```

O módulo resolve o ARN da sessão que roda o `apply` para conceder acesso admin ao
cluster. Esse `data "aws_iam_session_context" "current"` tem
`count = local.create ? 1 : 0` — ou seja, é **avaliado sempre**, e não está
condicionado a `enable_cluster_creator_admin_permissions`. Desligar essa flag e
declarar `access_entries` explicitamente não evita a chamada: a tentativa foi
feita e falhou de forma idêntica.

Foram verificadas as versões v20.24.0, v20.26, v20.28, v20.29, v20.30.1, v20.31.0,
v20.31.6, v20.33.1, v20.37.2, v21.0.0, v21.3.1 e v21.5.0 — **todas** chamam
`iam:GetRole` incondicionalmente.

**Decisão:** substituir o módulo por recursos nativos do provider
(`aws_eks_cluster`, `aws_eks_node_group`, `aws_eks_addon`,
`aws_eks_access_entry`, `aws_eks_access_policy_association`). O acesso admin ao
cluster passa a ser concedido por uma *access entry* explícita, com o ARN da role
derivado de `aws_caller_identity` por `regex` — sem nenhuma chamada a IAM:

```hcl
access_config {
  authentication_mode                         = "API"
  bootstrap_cluster_creator_admin_permissions = false
}
```

A alternativa seria forkar o módulo para remover o `data source`, o que criaria
uma dependência de manutenção própria para ganhar pouco: o módulo entrega
sobretudo conveniência de rede e IAM, e aqui a rede vem do `oficina-infra-db` e o
IAM está fixado na `LabRole`. Em ~135 linhas de recursos nativos o comportamento
fica explícito e legível.

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
- Sem o módulo, o que ele fazia de graça passa a ser responsabilidade do código:
  os addons (`vpc-cni`, `kube-proxy`, `coredns`), a ordem entre node group e
  addons, e a regra de security group que libera os nodes para o RDS estão
  declarados um a um em `oficina-infra-k8s/terraform/eks.tf`.
- O ALB Ingress Controller continua fora de alcance por outro motivo: ele exige
  IRSA, que depende de `iam:CreateRole`. O ingress usa **NLB** via anotações do
  `ingress-nginx`.
