# RFC-002 — Banco de dados

- **Status:** aceito
- **Data:** 2026-09-15
- **Autora:** Lorena Montes

## Contexto

Na Fase 2 o PostgreSQL rodava como StatefulSet dentro do cluster kind, com
volume efêmero: derrubar o cluster apagava os dados. A Fase 3 exige um **banco
gerenciado** provisionado por Terraform.

O domínio é o de uma oficina mecânica: clientes, veículos, catálogo de serviços,
insumos com controle de estoque, ordens de serviço com histórico de status e
orçamentos aprováveis por link.

## Alternativas consideradas

| Critério | RDS PostgreSQL | DynamoDB | DocumentDB |
|---|---|---|---|
| Modelo | relacional, ACID | chave-valor / documento | documento |
| Transação multi-tabela | nativa | limitada (`TransactWriteItems`, 100 itens, mesma região) | multi-documento desde a 4.0 |
| Integridade referencial | declarada no schema | inexistente | inexistente |
| Junções e agregações | SQL completo, funções de janela | exige desnormalizar ou Athena | agregação por pipeline |
| Tipo monetário exato | `numeric(10,2)` | `Number` (precisão decimal, mas sem escala fixa) | `Decimal128` |
| Custo no Learner Lab | `db.t3.micro` no free tier | sob demanda, baixo | mínimo ~USD 200/mês — inviável |
| Esforço de migração | zero (já era Postgres) | reescrita completa da camada de persistência | reescrita completa |

## Decisão

**Amazon RDS for PostgreSQL 16**, instância `db.t3.micro`, 20 GB gp3
criptografado, em subnet privada, sem acesso público.

O domínio é fortemente relacional e transacional. A operação central — aprovar
um orçamento — precisa, no mesmo átomo, mudar o status da OS, gravar o
histórico e **debitar o estoque de todos os insumos envolvidos**. Uma falha
parcial aí produz estoque incorreto, que é erro contábil, não de exibição. Isso
pede ACID.

Três características do modelo reforçam a escolha:

1. **Integridade referencial declarada no banco.** As dez tabelas de negócio se
   ligam por chave estrangeira, com `ON DELETE CASCADE` onde há composição real
   (`os_servicos`, `os_status` e `os_orcamentos` não existem sem a `os`) e
   restrição onde não há (não se apaga um serviço do catálogo em uso). Fora de
   um relacional, essa garantia viraria código de aplicação.
2. **Agregações analíticas como requisito de produto.** "Tempo médio de execução
   por status" é uma janela sobre `os_status` — trivial em SQL, custosa fora dele.
3. **Valores monetários exatos.** `servicos.valor`, `insumos.valor` e
   `os_orcamentos.valor_total` são `decimal(10,2)`. Ponto flutuante seria
   inaceitável.

E o volume não justifica abrir mão de junções: dezenas de milhares de OS por
ano, com schema estável.

**Por que gerenciado e não StatefulSet:** backup automático, storage
criptografado, patching, e a separação entre o ciclo de vida do dado e o do
cluster — que, no Learner Lab, é derrubado ao fim de cada sessão.

## Consequências

**Positivas.** Migração sem reescrever uma linha da camada de persistência: as
15 migrations e o Eloquent seguem iguais. O dado sobrevive ao `terraform destroy`
do cluster. Backup diário automático.

**Negativas, e como são tratadas.**

- *O RDS não é interrompido entre sessões do Learner Lab e continua cobrando.*
  Pausar com `aws rds stop-db-instance --db-instance-identifier oficina-db`
  (pausa por até 7 dias) sempre que não estiver em uso.
- *A Lambda precisa alcançar o banco na subnet privada.* Ela roda dentro da VPC,
  com security group próprio liberado no SG do RDS.
- *`db.t3.micro` aceita poucas conexões simultâneas.* O pool da Lambda é fixado
  em `max: 1` e reaproveitado entre invocações do mesmo container.
- *Instância única, sem Multi-AZ.* Aceito: é ambiente acadêmico, e Multi-AZ
  dobraria o custo. Em produção real, `multi_az = true`.

O diagrama ER, com a explicação de cada relacionamento, está em
[`docs/arquitetura/modelo-de-dados.md`](../arquitetura/modelo-de-dados.md).
