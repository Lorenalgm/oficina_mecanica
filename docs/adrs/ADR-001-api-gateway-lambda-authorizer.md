# ADR-001 — Padrão de comunicação: API Gateway com Lambda Authorizer

- **Status:** aceito
- **Data:** 2026-09-15

## Contexto

O tráfego externo precisa chegar à `oficina-api` no EKS, e as rotas sensíveis
precisam ser protegidas por um token emitido a partir do CPF. As opções de
comunicação entre a borda e o cluster eram: o Gateway autorizar e encaminhar,
o Gateway apenas encaminhar deixando a autorização com a aplicação, ou expor o
ingress diretamente sem Gateway.

## Decisão

**API Gateway HTTP API (v2)** como porta única, com **Lambda Authorizer do tipo
REQUEST** (payload 2.0, *simple responses*) em `ANY /api/{proxy+}`, e integração
`HTTP_PROXY` para o ingress do cluster. A rota `POST /auth` é pública e vai por
`AWS_PROXY` direto para a Lambda `authenticate`.

O token é validado **duas vezes**: no Authorizer e, de novo, pelo middleware
`ValidarJwt` da aplicação.

## Justificativa

**Por que HTTP API e não REST API:** custa cerca de um terço, tem latência
menor, e o Authorizer com *simple response* dispensa montar documento de
política IAM. Os recursos que só o REST API tem — chaves de API, planos de uso,
transformação de payload — não são necessários aqui.

**Por que autorizar na borda:** requisição sem token válido nunca chega ao
cluster, então não consome pod, conexão de banco nem capacidade do HPA. O
resultado do Authorizer fica em cache por 300s para o mesmo header
`Authorization`, o que reduz invocações em rajadas.

**Por que validar de novo na aplicação:** o Service do cluster é alcançável por
dentro da VPC sem passar pelo Gateway — confiar apenas na borda deixaria uma
porta lateral aberta. E, na prática, é o que permite subir a API inteira no kind
e testá-la sem AWS nenhuma, que é o modo de desenvolvimento do dia a dia. O
custo é uma verificação de assinatura em memória por requisição.

## Alternativas descartadas

**Gateway sem Authorizer, autorização só na aplicação.** Deixaria tráfego não
autenticado atravessar o NLB e ocupar pods, e desperdiçaria um recurso que o
enunciado pede explicitamente.

**Ingress exposto direto, sem Gateway.** Perderia throttling, log de acesso
centralizado e o ponto único de autorização — e não atenderia ao requisito.

**Authorizer do tipo JWT (nativo do HTTP API).** Seria mais simples, mas o
emissor precisaria expor um endpoint OIDC de descoberta com JWKS, o que exigiria
RS256 e infraestrutura de publicação de chave. Para HS256 com segredo
compartilhado ([RFC-003](../rfcs/RFC-003-estrategia-de-autenticacao.md)), o
Authorizer REQUEST é o caminho.

## Consequências

- Uma invocação de Lambda a mais por requisição não cacheada (~10ms).
- O `backend_base_url` do Gateway precisa apontar para um endereço alcançável
  pela AWS: o NLB do EKS quando o cluster está de pé, e o deploy no Railway
  durante o desenvolvimento, já que o kind local não é acessível de fora.
- O `X-Amzn-Trace-Id` injetado pelo Gateway é aproveitado pelo middleware
  `CorrelationId` como fonte do `correlation_id` quando não há `X-Request-Id`,
  amarrando o log de acesso do Gateway ao log da aplicação.
