# Autenticação por CPF

O cliente troca o CPF por um **token (JWT)** válido por 1 hora e usa esse
token nas demais chamadas. A Lambda não acessa o banco: consulta o cliente na
rota interna do OS Service.

## 1. Login com CPF

```mermaid
sequenceDiagram
    autonumber
    actor C as 👤 Cliente
    participant GW as 🚪 API Gateway
    participant L as 🪪 Lambda authenticate
    participant SM as 🔑 Secrets Manager
    participant OS as ⚙️ OS Service (rota interna)

    C->>GW: POST /auth com o CPF
    GW->>L: repassa o pedido

    opt primeira chamada da Lambda
        L->>SM: busca a chave do token e o token interno
        SM-->>L: segredos (ficam guardados para as próximas chamadas)
    end

    L->>L: confere os dígitos do CPF

    alt CPF inválido
        rect rgb(254, 226, 226)
            L-->>C: 400 · "CPF inválido"
        end
    else CPF válido
        L->>OS: GET /internal/clientes/por-documento/{cpf}<br/>com X-Internal-Token
        alt cliente não existe
            rect rgb(254, 243, 199)
                OS-->>L: 404
                L-->>C: 404 · "Cliente não encontrado"
            end
        else cliente encontrado
            rect rgb(220, 252, 231)
                OS-->>L: id e nome do cliente
                L->>L: gera o token (expira em 1h)
                L-->>C: 200 · token
            end
        end
    end
```

## 2. Chamada protegida usando o token

```mermaid
sequenceDiagram
    autonumber
    actor C as 👤 Cliente
    participant GW as 🚪 API Gateway
    participant AZ as 🛡️ Lambda authorizer
    participant API as ⚙️ Serviço (EKS)
    participant DB as 🐘 Banco (RDS)

    C->>GW: GET /api/os com o token
    GW->>AZ: este token é válido?
    AZ->>AZ: confere assinatura e validade

    alt token inválido ou expirado
        rect rgb(254, 226, 226)
            AZ-->>GW: não
            GW-->>C: 403 · acesso negado
        end
    else token válido
        rect rgb(220, 252, 231)
            AZ-->>GW: sim (resposta guardada por 5 min)
            GW->>API: encaminha a chamada
            API->>API: confere o token de novo
            API->>DB: consulta as OS
            DB-->>API: dados
            API-->>C: 200 · lista de OS
        end
    end
```

**Cores:** 🟩 sucesso · 🟨 cliente não encontrado · 🟥 erro de acesso
