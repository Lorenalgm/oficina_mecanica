# Event Storming — Oficina Mecânica

> **Legenda:** 🟠 EV = Evento | 🔵 CMD = Comando | 🟡 AG = Agregado | 🟣 POL = Política | 🟢 ML = Modelo de Leitura | 🔷 PA = Ponto de Atenção

---

## Fluxo 1 — Criação e Acompanhamento da OS

```mermaid
flowchart LR

    subgraph ATENDENTE [👤 Atendente]
        CMD1[CMD\nIdentificar\nCliente por\nCPF/CNPJ]:::cmd
        CMD2[CMD\nCadastrar\nVeículo]:::cmd
        CMD3[CMD\nCriar OS]:::cmd
        CMD4[CMD\nAdicionar\nServiço à OS]:::cmd
        CMD5[CMD\nAdicionar\nInsumo ao\nServiço]:::cmd
        CMD6[CMD\nGerar\nOrçamento]:::cmd
    end

    subgraph SISTEMA [⚙️ Sistema]
        AG1[AG\nCliente]:::ag
        EV1[EV\nCliente\nIdentificado]:::ev
        AG2[AG\nVeículo]:::ag
        EV2[EV\nVeículo\nCadastrado]:::ev
        AG3[AG\nOS]:::ag
        EV3[EV\nOS Criada\nRecebida]:::ev
        EV4[EV\nServiço\nAdicionado]:::ev
        PA1{PA\nEstoque\nsuficiente?}:::pa
        EV5ok[EV\nEstoque\nBaixado]:::ev
        EV5err[EV\nErro\nEstoque\nInsuficiente]:::ev
        EV6[EV\nOrçamento\nGerado\nAguard. Aprovação]:::ev
        POL1[POL\nEnviar orçamento\nao cliente]:::pol
    end

    subgraph CLIENTE [👤 Cliente]
        ML1[ML\nOrçamento\nRecebido]:::ml
        PA2{PA\nAprova\norçamento?}:::pa
        CMD7[CMD\nAprovar\nOrçamento]:::cmd
        CMD8[CMD\nRecusar\nOrçamento]:::cmd
        CMD_pub[CMD\nConsultar OS\npor Placa]:::cmd
        ML2[ML\nStatus Atual\nda OS]:::ml
        EV_pub[EV\nConsulta\nRealizada]:::ev
    end

    subgraph MECANICO [👤 Mecânico]
        CMD9[CMD\nAlterar Status\nEm Execução\n→ Finalizada]:::cmd
        CMD10[CMD\nAlterar Status\nFinalizada\n→ Entregue]:::cmd
    end

    subgraph SISTEMA2 [⚙️ Sistema]
        AG4[AG\nOS]:::ag
        EV7[EV\nOrçamento\nAprovado\nEm Execução]:::ev
        EV8[EV\nOrçamento\nRecusado\nCancelada]:::ev
        AG5[AG\nOS]:::ag
        EV9[EV\nOS\nFinalizada]:::ev
        AG6[AG\nOS]:::ag
        EV10[EV\nOS\nEntregue]:::ev
    end

    CMD1 --> AG1 --> EV1 --> CMD2
    CMD2 --> AG2 --> EV2 --> CMD3
    CMD3 --> AG3 --> EV3 --> CMD4
    CMD4 --> AG3 --> EV4 --> CMD5
    CMD5 --> PA1
    PA1 -->|sim| EV5ok --> CMD6
    PA1 -->|não| EV5err
    CMD6 --> AG3 --> EV6 --> POL1
    POL1 --> ML1
    ML1 --> PA2
    PA2 -->|aprova| CMD7 --> AG4 --> EV7
    PA2 -->|recusa| CMD8 --> EV8
    EV7 --> CMD9 --> AG5 --> EV9 --> CMD10 --> AG6 --> EV10
    CMD_pub --> ML2 --> EV_pub

    classDef cmd fill:#29ABE2,stroke:#1a7fa8,color:#fff
    classDef ev  fill:#F7941D,stroke:#c5760f,color:#fff
    classDef ag  fill:#FFF176,stroke:#c8b900,color:#333
    classDef pol fill:#9B59B6,stroke:#6c3483,color:#fff
    classDef ml  fill:#27AE60,stroke:#1a7a42,color:#fff
    classDef pa  fill:#F1948A,stroke:#b03a2e,color:#333
```

---

## Fluxo 2 — Gestão de Peças e Insumos

```mermaid
flowchart LR

    subgraph ADMIN [👤 Admin]
        CMD1[CMD\nCadastrar\nInsumo]:::cmd
        CMD2[CMD\nAtualizar\nInsumo]:::cmd
        CMD3[CMD\nRemover\nInsumo]:::cmd
        CMD_repo[CMD\nRepor\nEstoque]:::cmd
    end

    subgraph SISTEMA [⚙️ Sistema — CRUD]
        AG1[AG\nInsumo]:::ag
        EV1[EV\nInsumo\nCadastrado]:::ev
        ML1[ML\nLista de Insumos\nc/ Estoque Atual]:::ml
        AG1b[AG\nInsumo]:::ag
        EV2[EV\nInsumo\nAtualizado]:::ev
        PA_rem{PA\nVinculado\na uma OS?}:::pa
        EV3ok[EV\nInsumo\nRemovido]:::ev
        EV3err[EV\nErro — Insumo\nem uso na OS]:::ev
        AG_repo[AG\nInsumo]:::ag
        EV_repo[EV\nEstoque\nReposto]:::ev
    end

    subgraph ATENDENTE [👤 Atendente]
        CMD_os[CMD\nAdicionar Insumo\nao Serviço na OS]:::cmd
    end

    subgraph SISTEMA2 [⚙️ Sistema — Baixa Automática]
        POL1[POL\nVerificar\ndisponibilidade\nno estoque]:::pol
        PA_est{PA\nEstoque\nsuficiente?}:::pa
        CMD_baixa[CMD\nBaixar\nEstoque]:::cmd
        AG_baixa[AG\nInsumo]:::ag
        EV_baixa[EV\nEstoque\nBaixado]:::ev
        EV_err[EV\nErro — Estoque\nInsuficiente]:::ev
        PA_min{PA\nAbaixo do\nestoque mínimo?}:::pa
        EV_ok[EV\nEstoque\nNormal]:::ev
        EV_alerta[EV\nAlerta — Estoque\nBaixo]:::ev
        POL2[POL\nNotificar\nAdministrador]:::pol
    end

    CMD1 --> AG1 --> EV1 --> ML1
    CMD2 --> AG1b --> EV2
    CMD3 --> PA_rem
    PA_rem -->|não| EV3ok
    PA_rem -->|sim| EV3err

    CMD_os --> POL1
    POL1 --> PA_est
    PA_est -->|sim| CMD_baixa --> AG_baixa --> EV_baixa --> PA_min
    PA_est -->|não| EV_err
    PA_min -->|não| EV_ok
    PA_min -->|sim| EV_alerta --> POL2 --> CMD_repo
    CMD_repo --> AG_repo --> EV_repo

    classDef cmd fill:#29ABE2,stroke:#1a7fa8,color:#fff
    classDef ev  fill:#F7941D,stroke:#c5760f,color:#fff
    classDef ag  fill:#FFF176,stroke:#c8b900,color:#333
    classDef pol fill:#9B59B6,stroke:#6c3483,color:#fff
    classDef ml  fill:#27AE60,stroke:#1a7a42,color:#fff
    classDef pa  fill:#F1948A,stroke:#b03a2e,color:#333
```
