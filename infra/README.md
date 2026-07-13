# Infraestrutura como Código (Terraform)

Provisiona a **plataforma** que hospeda a aplicação: cluster Kubernetes local (kind), metrics-server (pré-requisito do HPA), namespace, Secret com variáveis sensíveis e o banco de dados Postgres.

Os manifestos da **aplicação** (Deployment, Service, HPA, ConfigMap, Job de migração) ficam em [`../k8s`](../k8s) e são aplicados via `kubectl` (localmente e no CI) — ver "Passo a passo".

## Pré-requisitos

- Docker em execução
- [`kind`](https://kind.sigs.k8s.io/), `kubectl`, `terraform` (>= 1.5) e `helm` instalados

## Recursos criados

| Recurso | Arquivo | Descrição |
|---|---|---|
| `kind_cluster.this` | `cluster.tf` | Cluster Kubernetes local (1 control-plane + 1 worker), com port mapping `8080→30080` para Ingress opcional |
| `helm_release.metrics_server` | `metrics-server.tf` | metrics-server (`--kubelet-insecure-tls` para o kind) — habilita o HPA |
| `kubernetes_namespace_v1.oficina` | `namespace.tf` | Namespace `oficina` |
| `kubernetes_secret_v1.app` | `secret.tf` | Secret `oficina-api-secrets` (`APP_KEY`, `DB_PASSWORD`, `RESEND_API_KEY`) a partir de variáveis |
| `null_resource.postgres` | `database.tf` | Aplica o Postgres (`../k8s/postgres.yaml`: StatefulSet + Service + PVC) e aguarda o rollout |

> **Nuvem:** `database.tf` inclui, comentado, um exemplo de banco gerenciado (`aws_db_instance`) — a forma como o banco seria provisionado inteiramente pelo Terraform em produção.

## Variáveis sensíveis

Definidas em `variables.tf` e fornecidas **fora do versionamento**:

- **Local:** copie `terraform.tfvars.example` para `terraform.tfvars` (git-ignored) e preencha.
- **CI:** exporte como `TF_VAR_app_key`, `TF_VAR_db_password`, `TF_VAR_resend_api_key` a partir de **GitHub Secrets**.

Gere a `APP_KEY` com: `php artisan key:generate --show`.

## Passo a passo

```bash
cd infra

# 1. Provisionar a plataforma (cluster + metrics-server + namespace + secret + banco)
terraform init
terraform apply        # usa terraform.tfvars

# 2. Apontar o kubectl para o cluster (o kind já mescla no ~/.kube/config)
kubectl cluster-info --context kind-oficina

# 3. Construir a imagem de produção e carregá-la no kind
#    (localmente não puxamos do GHCR — usamos a imagem local)
docker build -f ../docker/app/Dockerfile -t ghcr.io/lorenalgm/oficina-api:latest ..
kind load docker-image ghcr.io/lorenalgm/oficina-api:latest --name oficina

# 4. Implantar a aplicação (manifestos em ../k8s)
kubectl apply -k ../k8s/
kubectl -n oficina rollout status deployment/oficina-api

# 5. Acessar a API
kubectl -n oficina port-forward svc/oficina-api 8080:80
# -> http://localhost:8080/up

# 6. Destruir tudo
terraform destroy
```

O `terraform destroy` remove o cluster kind inteiro — sem efeitos colaterais em outros ambientes (o deploy no Railway é independente e não é tocado).
