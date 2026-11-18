# Modelo de dados

Banco **PostgreSQL 16** (Amazon RDS) do **OS Service**. O diagrama foi montado a
partir das migrations em [`database/migrations`](../../database/migrations).
Orçamento e pagamento ficam no banco do Billing; a execução, no MongoDB da
Execução.

São **11 tabelas de negócio**, em quatro grupos:

| Grupo | Tabelas | O que guardam |
|---|---|---|
| 👤 Cadastro | `clientes`, `veiculos` | Quem é o cliente e quais carros ele tem |
| 🔧 Catálogo | `servicos`, `insumos`, `status` | O que a oficina oferece e os estados possíveis de uma OS |
| 🛠️ Atendimento | `os`, `os_servicos`, `os_servico_insumos`, `os_status` | A ordem de serviço e tudo o que acontece com ela |
| 🔁 Saga | `sagas`, `saga_passos` | Estado da Saga de cada OS e a linha do tempo dos passos |

```mermaid
erDiagram
  clientes ||--o{ veiculos : "possui"
  clientes ||--o{ os : "abre"
  veiculos ||--o{ os : "recebe"
  status   ||--o{ os : "é o status atual de"
  os       ||--o{ os_servicos : "inclui"
  servicos ||--o{ os_servicos : "é usado em"
  os_servicos ||--o{ os_servico_insumos : "consome"
  insumos     ||--o{ os_servico_insumos : "é consumido em"
  os       ||--o{ os_status : "tem histórico em"
  status   ||--o{ os_status : "aparece em"
  os       ||--o{ sagas : "é acompanhada por"
  sagas    ||--o{ saga_passos : "registra"

  clientes {
    bigint id PK
    string nome
    string documento UK "CPF ou CNPJ, usado no login"
    string celular "opcional"
    string email "opcional"
  }
  veiculos {
    bigint id PK
    string placa UK
    string marca
    string modelo
    int    ano
    bigint cliente_id FK
  }
  servicos {
    bigint  id PK
    string  nome
    decimal valor "10,2"
  }
  insumos {
    bigint  id PK
    string  nome
    decimal valor "10,2"
    int     quantidade_estoque "padrão 0"
  }
  status {
    bigint id PK
    string nome "Recebida ... Entregue"
  }
  os {
    bigint id PK
    bigint veiculo_id FK
    bigint cliente_id FK
    bigint status_atual_id FK
    text   descricao_problema
    string filial "padrão MATRIZ"
  }
  os_servicos {
    bigint id PK
    bigint os_id FK
    bigint servico_id FK
  }
  os_servico_insumos {
    bigint id PK
    bigint os_servico_id FK
    bigint insumo_id FK
    int    quantidade
  }
  os_status {
    bigint    id PK
    bigint    os_id FK
    bigint    status_id FK
    timestamp data_status
  }
  sagas {
    uuid    id PK
    bigint  os_id FK
    string  correlation_id
    string  estado
    bigint  orcamento_id "id no Billing"
    bigint  pagamento_id "id no Billing"
    string  execucao_id "id na Execução"
    boolean estoque_baixado
    json    compensacoes_pendentes
    string  motivo_falha "opcional"
  }
  saga_passos {
    bigint    id PK
    uuid      saga_id FK
    string    passo
    json      detalhes
    timestamp registrado_em
  }
```

> Colunas `created_at` / `updated_at` foram omitidas. Existem em `clientes`,
> `veiculos` e `os`.

## Relacionamentos

| Relação | Cardinalidade | Ao apagar o pai | Por quê |
|---|---|---|---|
| `clientes` → `veiculos` | 1 : N | apaga os veículos | Veículo não existe sem dono |
| `clientes` → `os` | 1 : N | **bloqueia** | Não se perde o histórico de atendimento |
| `veiculos` → `os` | 1 : N | **bloqueia** | Idem |
| `status` → `os` | 1 : N | **bloqueia** | Status é tabela de referência |
| `os` → `os_servicos` | 1 : N | apaga | Serviço da OS só faz sentido dentro dela |
| `servicos` → `os_servicos` | 1 : N | **bloqueia** | Não se apaga do catálogo um serviço em uso |
| `os_servicos` → `os_servico_insumos` | 1 : N | apaga | A peça pertence ao serviço daquela OS |
| `insumos` → `os_servico_insumos` | 1 : N | **bloqueia** | Não se apaga uma peça em uso |
| `os` → `os_status` | 1 : N | apaga | Histórico da própria OS |
| `status` → `os_status` | 1 : N | **bloqueia** | Tabela de referência |
| `os` → `sagas` | 1 : N | apaga | Uma OS pode ter nova Saga depois de uma compensada; só uma ativa por vez |
| `sagas` → `saga_passos` | 1 : N | apaga | Linha do tempo da própria Saga |

## Pontos que merecem explicação

- **Status atual e histórico.** `os_status` guarda cada mudança de status com a
  data. É dela que sai o tempo médio por status. `os.status_atual_id` repete o
  último status só para a listagem de OS não precisar consultar o histórico
  linha a linha.
- **Peças ficam ligadas ao serviço, não à OS.** O caminho é
  `os → os_servicos → os_servico_insumos`. Assim se sabe qual peça foi usada em
  qual serviço, e remover um serviço remove as peças dele.
- **Saga guarda só referências aos outros serviços.** `orcamento_id`,
  `pagamento_id` e `execucao_id` são ids que vieram nas mensagens; não há chave
  estrangeira para outro banco.
- **`clientes.documento` é único** porque é a chave da autenticação: a Lambda
  consulta o cliente pelo CPF na rota interna, e ele precisa apontar para um
  cliente só.
- **Valores em `decimal(10,2)`**, nunca ponto flutuante, para não haver erro de
  arredondamento em dinheiro.

## Tabelas fora do domínio

Criadas pelo Laravel e não usadas pelas regras da oficina: `users`,
`password_reset_tokens`, `sessions`, `personal_access_tokens` (login antigo da
Fase 2, via Sanctum), `cache`, `cache_locks`, `jobs`, `job_batches` e `failed_jobs`.

Da mensageria: `outbox` (mensagens gravadas na mesma transação e publicadas
depois) e `mensagens_processadas` (garante que uma mensagem repetida não seja
processada duas vezes). `os_orcamentos` é da Fase 3 e não é mais usada: o
orçamento passou para o Billing.
