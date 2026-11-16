# language: pt
Funcionalidade: Saga da ordem de serviço
  Para manter OS, cobrança e estoque consistentes entre os microsserviços
  Como oficina
  Quero que a Saga desfaça o que já foi feito quando um passo falhar

  Contexto:
    Dado o serviço "Troca de óleo" de R$ 100,00
    E o insumo "Óleo 5W30" de R$ 49,90 com 10 unidades em estoque
    E uma OS da cliente "Ana Souza" com "Troca de óleo" usando 2 "Óleo 5W30"

  Cenário: Caminho feliz até a OS finalizada
    Quando o atendente solicita o orçamento
    Então a OS fica "Em diagnóstico"
    E o Billing recebe o pedido de orçamento de R$ 199,80
    Quando o Billing gera o orçamento
    Então a OS fica "Aguardando aprovação"
    E a cliente recebe por e-mail o link de aprovação
    Quando a cliente aprova o orçamento
    Então o estoque de "Óleo 5W30" fica em 8
    E a OS fica "Aguardando pagamento"
    E o Billing recebe o pedido de cobrança de R$ 199,80
    Quando o Billing cria a cobrança
    Então a cliente recebe por e-mail o link de pagamento
    Quando o pagamento é confirmado
    Então a OS fica "Em execução"
    E a Execução recebe a OS na fila da filial "MATRIZ"
    Quando a Execução enfileira a OS
    Então a Saga fica "concluida"
    Quando a Execução finaliza o reparo
    Então a OS fica "Finalizada"

  Esquema do Cenário: Falha em um passo desfaz o que já foi feito
    Dado que a Saga chegou até "<etapa>"
    Quando <falha>
    E o Billing confirma as compensações pedidas
    Então a OS fica "Cancelada"
    E a Saga fica "compensada"
    E o estoque de "Óleo 5W30" fica em 10
    E as compensações enviadas ao Billing foram "<compensacoes>"

    Exemplos:
      | etapa                | falha                                    | compensacoes                  |
      | pedido de orçamento  | o Billing não consegue gerar o orçamento | nenhuma                       |
      | orçamento gerado     | a cliente recusa o orçamento             | nenhuma                       |
      | cobrança criada      | o pagamento é recusado                   | cancelar-orcamento            |
      | pagamento confirmado | a Execução rejeita a OS por fila cheia   | estornar, cancelar-orcamento  |

  Cenário: Estoque insuficiente na aprovação cancela orçamento e OS
    Dado que o estoque de "Óleo 5W30" é de 1 unidade
    E que a Saga chegou até "orçamento gerado"
    Quando a cliente aprova o orçamento
    E o Billing confirma as compensações pedidas
    Então a OS fica "Cancelada"
    E o estoque de "Óleo 5W30" fica em 1
    E as compensações enviadas ao Billing foram "cancelar-orcamento"

  Cenário: Estorno indisponível deixa a Saga para ação manual
    Dado que a Saga chegou até "pagamento confirmado"
    Quando a Execução rejeita a OS por fila cheia
    E o estorno falha 3 vezes
    Então a Saga fica "compensacao_falhou"
    E a OS fica "Em execução"
    E o estoque de "Óleo 5W30" fica em 8
