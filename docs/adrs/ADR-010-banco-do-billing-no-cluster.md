# ADR-010 — PostgreSQL do Billing no cluster em vez de RDS

- **Status:** aceito
- **Data:** 2026-11-16

## Contexto

O Billing Service precisa de banco próprio. O plano inicial previa uma
instância RDS dedicada, criada pelo Terraform do repositório do Billing. No
AWS Academy, a conta é zerada a cada challenge e as permissões são limitadas à
`LabRole` ([ADR-004](ADR-004-labrole-no-aws-academy.md)), o que já tornava o
RDS do OS Service o recurso mais lento de recriar.

## Decisão

PostgreSQL 16 em **StatefulSet com volume** no cluster, declarado em
`k8s/postgres.yaml` do próprio `oficina-billing-service` e aplicado pelo mesmo
deploy do serviço.

## Justificativa

**Banco e infra do serviço no próprio repositório.** O deploy do Billing sobe
o banco, as migrações (Job) e os processos do serviço juntos, sem depender de
outra pipeline.

**Simplicidade.** Igual no kind e no EKS, sem Terraform, sub-redes ou grupos
de segurança novos, e sem mais uma instância RDS para pagar e recriar.

**Isolamento mantido.** É uma instância separada, com usuário e credenciais
próprios. Nenhum outro serviço acessa esse banco.

## Alternativas descartadas

**RDS próprio.** Mais robusto (backup, failover gerenciado), mas mais caro e
lento de recriar no Learner Lab.

**Outro schema no RDS do OS.** Mais simples ainda, mas os dois serviços
dividiriam a mesma instância, o que enfraquece a separação pedida.

## Consequências

- Backup e alta disponibilidade ficam por nossa conta; com 1 réplica, uma
  falha do pod deixa o Billing sem banco até o pod voltar. Aceitável para o
  desafio, não para produção.
- O volume depende do driver de armazenamento do cluster (EBS CSI no EKS).
- Migrar para RDS depois é trocar host e credenciais no Secret.
