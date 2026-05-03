# Oficina Mecânica API

MVP do sistema integrado de atendimento e execução de serviços — back-end em Laravel 11.

## Stack

- **PHP 8.2+** / **Laravel 11**
- **PostgreSQL 16** — integridade referencial robusta, suporte nativo a CHECK constraints, performance em queries de agregação (tempo médio de execução), compatibilidade total com Laravel
- **Laravel Sanctum** — autenticação token-based simples para APIs administrativas
- **L5-Swagger (OpenAPI 3.0)** — documentação das APIs em `/api/documentation`
- **PHPUnit** com SQLite em memória nos testes

## Arquitetura

Monolito em camadas com DDD:

```
src/
├── Domain/       # Entidades, Value Objects, interfaces de repositório
├── Application/  # Use Cases
└── Infrastructure/
    └── Persistence/Eloquent/  # Models, Repositories, Mappers
```

## Como rodar com Docker

```bash
cp .env.example .env
# ajuste .env se necessário (DB_HOST=db já configurado)

docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

A API ficará disponível em `http://localhost:8000`.
Documentação Swagger: `http://localhost:8000/api/documentation`

## Como rodar localmente (sem Docker)

```bash
cp .env.example .env
# configure DB_HOST=127.0.0.1 e crie o banco manualmente

composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Como rodar os testes

```bash
php artisan test
# ou com cobertura:
php artisan test --coverage --min=80
```

Os testes usam SQLite em memória — nenhuma configuração de banco necessária.

## Endpoints principais

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| POST | `/api/auth/login` | Não | Login |
| POST | `/api/auth/logout` | Sim | Logout |
| GET/POST | `/api/clientes` | Sim | CRUD clientes |
| GET/POST | `/api/veiculos` | Sim | CRUD veículos |
| GET/POST | `/api/servicos` | Sim | CRUD serviços |
| GET/POST | `/api/insumos` | Sim | CRUD insumos/peças |
| POST | `/api/os` | Sim | Criar OS |
| PATCH | `/api/os/{id}/status` | Sim | Alterar status |
| POST | `/api/os/{id}/orcamento/aprovar` | Sim | Aprovar orçamento |
| GET | `/api/consulta-publica` | Não | Status da OS pelo cliente |

Documentação completa: `GET /api/documentation`

## Bounded Contexts

- **Identidade**: Cliente, Veículo
- **Atendimento**: OS, Orçamento, Histórico de Status
- **Catálogo**: Serviço, Insumo (com controle de estoque)

> Usuário (`User`) vive apenas como infraestrutura de autenticação (Sanctum) — não pertence ao domínio da oficina.
