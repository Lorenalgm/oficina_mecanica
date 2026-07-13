variable "cluster_name" {
  description = "Nome do cluster kind."
  type        = string
  default     = "oficina"
}

variable "namespace" {
  description = "Namespace Kubernetes da aplicação."
  type        = string
  default     = "oficina"
}

variable "k8s_manifests_path" {
  description = "Caminho para os manifestos Kubernetes (relativo a este módulo)."
  type        = string
  default     = "../k8s"
}

# --- Variáveis sensíveis (fornecidas via terraform.tfvars git-ignored ou TF_VAR_* no CI) ---

variable "app_key" {
  description = "APP_KEY do Laravel (formato base64:...)."
  type        = string
  sensitive   = true
}

variable "db_password" {
  description = "Senha do banco Postgres."
  type        = string
  sensitive   = true
}

variable "resend_api_key" {
  description = "Token do serviço externo Resend."
  type        = string
  sensitive   = true
  default     = ""
}
