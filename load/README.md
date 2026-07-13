# Teste de carga (k6) e demonstração de autoscaling

O script [`load-test.js`](./load-test.js) gera carga na API para disparar o **Horizontal Pod Autoscaler (HPA)**.

## Pré-requisitos

- [k6](https://k6.io/docs/get-started/installation/) instalado
- Aplicação implantada no cluster e acessível (ver [`../infra/README.md`](../infra/README.md))
- HPA e metrics-server ativos (`kubectl -n oficina get hpa` deve mostrar métrica de CPU, não `<unknown>`)

## Como rodar

1. Exponha a API (em um terminal):
   ```bash
   kubectl -n oficina port-forward svc/oficina-api 8080:80
   ```

2. Observe o autoscaling (em outro terminal):
   ```bash
   kubectl -n oficina get hpa -w
   kubectl -n oficina get pods -w
   kubectl -n oficina top pods
   ```

3. Dispare a carga (em um terceiro terminal):
   ```bash
   BASE_URL=http://localhost:8080 k6 run load/load-test.js
   ```

## O que esperar

- Durante o _ramp-up_ e a sustentação (≈ 4 min), a CPU dos pods ultrapassa o alvo (50%).
- O HPA aumenta as réplicas do Deployment `oficina-api` (de 2 em direção a 10).
- Ao cessar a carga, após a janela de estabilização, o HPA reduz as réplicas de volta ao mínimo (2).

O script autentica com o usuário semeado (`admin@oficina.com` / `password`), cria um cliente e um veículo e então gera **múltiplas ordens de serviço** (`POST /api/os`) junto com listagens — simulando o cenário de carga sugerido pelo enunciado.
