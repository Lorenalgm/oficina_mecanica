# ADR-009 — Mercado Pago como mock atrás da porta GatewayPagamento

- **Status:** aceito
- **Data:** 2026-11-16

## Contexto

O passo de pagamento da Saga precisa criar a cobrança, saber se foi paga ou
recusada e estornar. O Mercado Pago é o meio previsto, mas usá-lo de verdade
exige conta de desenvolvedor, credenciais de teste e um endpoint público para o
webhook. A demonstração também precisa provocar a recusa sob demanda para
mostrar a compensação.

## Decisão

O Billing Service define a porta `GatewayPagamento`
(`src/Domain/Cobranca/GatewayPagamento.php`) com `criarCobranca` e `estornar`.
O adaptador em uso é o `FakeGateway`, que simula o Mercado Pago. A rota
`POST /api/pagamentos/{id}/simular` faz o papel do webhook: confirma ou recusa
o pagamento e o Billing publica `pagamento-confirmado` ou `pagamento-recusado`.

É uma decisão deliberada: o pagamento real não faz parte do que o desafio
avalia, e o mock deixa o fluxo reproduzível.

## Justificativa

**Demo e testes determinísticos.** Recusa e confirmação acontecem quando
pedimos, sem depender de cartões de teste ou da disponibilidade do sandbox.

**A troca fica isolada.** Os casos de uso `Cobrar` e `Estornar` dependem só da
porta. O resto do Billing e a Saga não mudam.

## Como trocar pelo adaptador real

1. Criar `MercadoPagoGateway` implementando `GatewayPagamento`: preferência do
   Checkout Pro em `criarCobranca` (devolvendo o link de pagamento) e a API de
   reembolso em `estornar`.
2. Criar a rota de webhook que valida o `x-signature`, consulta o pagamento no
   Mercado Pago e chama o mesmo caso de uso usado hoje pela rota `simular`.
3. Trocar o *binding* no `AppServiceProvider` (por variável de ambiente) e
   colocar as credenciais no Secret do Billing.
4. Publicar a rota de webhook no API Gateway como rota pública.

## Consequências

- Nenhum dinheiro ou dado de cartão passa pelo sistema.
- A rota `simular` não deve existir em produção real; ela sai junto com a
  troca do adaptador.
