# RFC-003 — Estratégia de autenticação

- **Status:** aceito
- **Data:** 2026-09-15
- **Autora:** Lorena Montes

## Contexto

A Fase 2 autenticava com **Laravel Sanctum**: o usuário fazia login com e-mail e
senha e recebia um *token opaco*, cuja validação exigia consultar a tabela
`personal_access_tokens` a cada requisição.

A Fase 3 muda o requisito: o cliente se identifica **pelo CPF**, sem senha, e
quem valida é uma **função serverless** que consulta o banco e devolve um token.
As rotas sensíveis passam a ser protegidas no **API Gateway**.

Isso quebra o modelo do Sanctum por dois motivos. Primeiro, o Authorizer do
Gateway roda numa Lambda que não tem — e não deve ter — acesso à sessão do
Laravel; validar um token opaco ali significaria uma consulta ao banco por
requisição, na borda. Segundo, o cliente final não tem senha: a identidade é o
CPF, já `UNIQUE` em `clientes.documento`.

## Alternativas consideradas

| Critério | JWT HS256 | JWT RS256 | Sanctum (token opaco) | Amazon Cognito |
|---|---|---|---|---|
| Validação sem I/O | sim | sim | **não** — consulta o banco | sim |
| Gestão de chaves | um segredo compartilhado | par de chaves, JWKS público | — | gerenciada pela AWS |
| Emissor e validador | mesmos donos (nós) | podem ser terceiros | — | AWS |
| Revogação imediata | não (só pela expiração) | não | sim | parcial |
| Suporte no Learner Lab | total | total | — | não está na lista de serviços liberados |
| Esforço | baixo | médio (distribuir JWKS) | — | alto (user pool, fluxo sem senha) |

## Decisão

**JWT HS256**, emitido pela Lambda `authenticate` e validado em dois pontos: o
Lambda Authorizer do API Gateway e o middleware `ValidarJwt` da aplicação.

O token carrega `iss` (`oficina-auth`), `sub` (id do cliente), `cpf`, `nome`,
`iat` e `exp` (1 hora). O segredo é gerado por `random_password` no Terraform de
`oficina-infra-db`, guardado no Secrets Manager e lido pelos dois lados — nunca
versionado.

**Por que HS256 e não RS256:** emissor e validador são o mesmo dono e rodam na
mesma conta. RS256 existe para que terceiros validem sem poder emitir, o que
aqui não se aplica; em troca, exigiria publicar e rotacionar um JWKS. HS256 com
um segredo no Secrets Manager é a opção proporcional.

**Por que o Cognito ficou de fora:** não consta dos serviços liberados no
Learner Lab, e um fluxo sem senha nele exigiria *custom auth challenge* — mais
complexidade do que o desafio pede.

### O token é validado duas vezes

Não é redundância acidental. O Authorizer rejeita tráfego não autenticado na
borda, antes de consumir capacidade do cluster, e o resultado fica em cache por
300s. O middleware repete a verificação porque o Service do cluster é alcançável
por dentro da VPC sem passar pelo Gateway, e porque permite rodar a API inteira
no kind, sem AWS, durante o desenvolvimento. Detalhado no
[ADR-001](../adrs/ADR-001-api-gateway-lambda-authorizer.md).

### O Sanctum continua existindo

`POST /api/login` e `POST /api/logout` seguem no Sanctum, para o painel interno
com e-mail e senha. São coisas diferentes: o JWT identifica **o cliente da
oficina** pelo CPF; o Sanctum identifica **o operador**. Um token do Sanctum não
abre rota protegida por JWT, e há teste garantindo isso
(`AuthTest::test_token_do_sanctum_nao_abre_rota_protegida_por_jwt`).

## Consequências

**Positivas.** Validação sem I/O: o Authorizer verifica assinatura e expiração
em memória. Identidade propagada em `context` pelo Gateway e em
`request->attributes` pelo Laravel, disponível para os logs. A API continua
funcionando isolada no kind.

**Negativas, e como são tratadas.**

- *Não há revogação imediata.* Um token vazado vale até expirar. Mitigado pelo
  TTL de 1 hora. Se fosse necessário revogar, a saída seria uma denylist de
  `jti` em cache — desproporcional para este escopo.
- *Relógios de Lambda e cluster podem divergir.* Ambos os lados aceitam 30s de
  tolerância (`clockTolerance` no `jose`, `leeway` no `firebase/php-jwt`).
- *O segredo precisa ser idêntico nos dois lados.* Por isso é gerado uma única
  vez no Terraform do banco e lido do Secrets Manager por ambos.
- *Autenticar só por CPF é fraco* — quem souber o CPF entra. Aceito porque é o
  que o enunciado especifica; em produção exigiria um segundo fator.

## Referências

- [ADR-001 — API Gateway com Lambda Authorizer](../adrs/ADR-001-api-gateway-lambda-authorizer.md)
- [Sequência de autenticação](../arquitetura/sequencia-auth.md)
