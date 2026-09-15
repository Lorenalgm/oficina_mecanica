# ADR-002 — Escalabilidade com HorizontalPodAutoscaler

- **Status:** aceito
- **Data:** 2026-09-15

## Contexto

O enunciado exige um cluster Kubernetes **com escalabilidade**. A demanda de uma
oficina é irregular: concentra-se na abertura de OS pela manhã e nas consultas de
status ao longo do dia, com vales longos. Provisionar para o pico significaria
pagar ocioso — inaceitável sob o teto de USD 50 do Learner Lab.

## Decisão

**HorizontalPodAutoscaler** (`autoscaling/v2`) sobre o Deployment da
`oficina-api`, de **2 a 10 réplicas**, com dois gatilhos: **50% de CPU média** e
**70% de memória média**, alimentado pelo `metrics-server`. Abaixo dele, o
managed node group do EKS varia de 2 a 4 nodes `t3.medium`.

## Justificativa

**Por que horizontal e não vertical.** A aplicação é stateless: sessão e cache
não vivem em memória de pod, então uma réplica nova atende igual a uma antiga.
Escalar horizontalmente também melhora a disponibilidade — o VPA precisaria
reiniciar o pod para mudar os *requests*, o que é justamente o que não se quer
sob carga.

**Por que CPU e memória, e não uma métrica de negócio.** O gargalo do
FrankenPHP sob carga é CPU: renderização de resposta, serialização e hashing
dominam. A memória entra como segundo gatilho porque o worker mode do FrankenPHP
mantém a aplicação carregada entre requisições, e um vazamento apareceria ali
antes de aparecer na CPU. Requisições por segundo seria uma métrica derivada, e
exigiria o adaptador de métricas externas do New Relic — complexidade sem ganho
aqui. Quando os dois gatilhos discordam, o HPA usa o que pede mais réplicas.

**Por que 50% de CPU.** Abaixo disso o HPA oscila com ruído; acima, o pod novo
demora a ficar pronto e o p95 sobe antes de a réplica entrar. 50% é conservador
de propósito: dá margem para o tempo de *startup* do FrankenPHP mais o
*readiness probe*, e o custo de uma réplica extra é baixo perto do de uma
requisição lenta na demonstração.

**Por que mínimo 2.** Uma réplica só significa indisponibilidade durante
qualquer rollout ou reinício de node — e o Learner Lab encerra as instâncias EC2
ao fim de cada sessão.

**Por que máximo 10.** É o teto que o node group consegue acomodar ao escalar
para 4 nodes `t3.medium` junto com ingress, metrics-server e o agente do New
Relic. Na prática o gatilho de CPU estabiliza bem antes disso; o teto existe
para que uma rajada anômala não arraste o node group — e o orçamento — junto.

## Pré-requisito

O HPA não funciona sem `metrics-server`, que o EKS não traz de fábrica — ele é
instalado por Helm no Terraform de `oficina-infra-k8s`. E não funciona sem
`resources.requests.cpu` declarado no Deployment: sem ele não há percentual a
calcular. É também por isso que o `migrate-job` usa label distinta do Deployment:
se o seletor casasse com o pod de migração, que não declara requests, o cálculo
do HPA quebraria.

## Verificação

```bash
kubectl -n oficina get hpa
k6 run load/script.js            # gera carga
kubectl -n oficina get pods -w   # réplicas subindo
```

No New Relic, o painel de CPU/memória por container mostra o efeito:

```sql
SELECT average(cpuUsedCores) FROM K8sContainerSample
WHERE clusterName = 'oficina-eks' FACET podName TIMESERIES
```

## Consequências

- Escala reativa: há um atraso entre o pico e a réplica pronta. Aceito para este
  perfil de carga; um pico instantâneo veria latência elevada por alguns segundos.
- O `scale down` tem estabilização padrão de 5 minutos, o que evita o vai-e-vem
  de réplicas mas mantém pods a mais por um tempo depois do pico.
