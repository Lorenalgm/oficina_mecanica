# Diagrama de sequência — autenticação por CPF

```mermaid
sequenceDiagram
    autonumber
    actor C as Cliente
    participant GW as API Gateway
    participant L as Lambda authenticate
    participant SM as Secrets Manager
    participant DB as RDS PostgreSQL

    C->>GW: POST /auth { "cpf": "529.982.247-25" }
    GW->>L: invoca (AWS_PROXY)

    alt primeira invocação do container
        L->>SM: GetSecretValue(oficina/app)
        SM-->>L: { DB_*, JWT_SECRET }
        Note over L: guarda em cache no módulo;<br/>invocações seguintes não repetem
    end

    L->>L: normaliza e valida os dígitos do CPF

    alt CPF inválido
        L-->>GW: 400 { "erro": "CPF inválido" }
        GW-->>C: 400
    else CPF válido
        L->>DB: SELECT id, nome FROM clientes WHERE documento = $1
        alt cliente não encontrado
            DB-->>L: 0 linhas
            L-->>GW: 404 { "erro": "Cliente não encontrado" }
            GW-->>C: 404
        else cliente encontrado
            DB-->>L: { id, nome }
            L->>L: assina JWT HS256 (iss, sub, cpf, nome, iat, exp=1h)
            L-->>GW: 200 { token, expires_in }
            GW-->>C: 200 + token
        end
    end
```

## Uso do token numa rota protegida

```mermaid
sequenceDiagram
    autonumber
    actor C as Cliente
    participant GW as API Gateway
    participant AZ as Lambda authorizer
    participant NG as ingress-nginx (NLB)
    participant API as oficina-api
    participant DB as RDS PostgreSQL

    C->>GW: GET /api/os  (Authorization: Bearer <jwt>)
    GW->>AZ: Authorizer REQUEST (payload 2.0)
    AZ->>AZ: jwtVerify HS256, issuer, exp (clockTolerance 30s)

    alt token inválido ou expirado
        AZ-->>GW: { isAuthorized: false }
        GW-->>C: 403
    else token válido
        AZ-->>GW: { isAuthorized: true, context: { clienteId, cpf } }
        Note over GW: resultado fica em cache por 300s<br/>para o mesmo Authorization
        GW->>NG: HTTP_PROXY /api/os + X-Amzn-Trace-Id
        NG->>API: GET /api/os
        API->>API: CorrelationId: X-Request-Id ou Root= do trace
        API->>API: ValidarJwt revalida o mesmo token
        API->>DB: consulta
        DB-->>API: dados
        API->>API: LogRequest emite JSON com duration_ms e correlation_id
        API-->>NG: 200 + X-Request-Id
        NG-->>GW: 200
        GW-->>C: 200
    end
```
