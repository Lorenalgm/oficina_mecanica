# Banco de dados (Postgres) — provisionado pelo Terraform no cluster.
#
# Abordagem: o Terraform orquestra o deploy do manifesto único
# k8s/postgres.yaml (StatefulSet + Service + PVC), reaproveitando a mesma
# definição usada no caminho "kubectl puro" (sem duplicar spec).
# O banco usa o Secret (senha) e o Namespace provisionados acima.
resource "null_resource" "postgres" {
  triggers = {
    manifest = filemd5("${path.module}/${var.k8s_manifests_path}/postgres.yaml")
  }

  provisioner "local-exec" {
    command = <<-EOT
      kubectl --kubeconfig "${kind_cluster.this.kubeconfig_path}" apply -f "${path.module}/${var.k8s_manifests_path}/postgres.yaml"
      kubectl --kubeconfig "${kind_cluster.this.kubeconfig_path}" -n ${var.namespace} rollout status statefulset/postgres --timeout=180s
    EOT
  }

  depends_on = [
    kubernetes_namespace_v1.oficina,
    kubernetes_secret_v1.app,
  ]
}

# ---------------------------------------------------------------------------
# Evolução para a nuvem (não usado no ambiente local):
# em produção, o banco seria um serviço gerenciado provisionado inteiramente
# pelo Terraform, por exemplo (AWS RDS):
#
# resource "aws_db_instance" "postgres" {
#   engine            = "postgres"
#   engine_version    = "16"
#   instance_class    = "db.t3.micro"
#   allocated_storage = 20
#   db_name           = "oficina"
#   username          = "oficina"
#   password          = var.db_password
#   skip_final_snapshot = true
# }
#
# Nesse caso, o DB_HOST no ConfigMap apontaria para aws_db_instance.postgres.address.
# ---------------------------------------------------------------------------
