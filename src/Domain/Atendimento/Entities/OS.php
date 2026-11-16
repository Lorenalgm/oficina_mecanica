<?php

namespace Domain\Atendimento\Entities;

use DateTimeInterface;

class OS
{
    public const FILIAL_PADRAO = 'MATRIZ';

    private ?string $statusAtualNome = null;
    private ?string $clienteNome = null;
    private ?string $veiculoPlaca = null;
    private ?DateTimeInterface $createdAt = null;
    private ?array $servicos = null;
    private ?array $historicoStatus = null;

    public function __construct(
        private readonly ?int $id,
        private int $clienteId,
        private int $veiculoId,
        private int $statusAtualId,
        private string $descricaoProblema,
        private string $filial = self::FILIAL_PADRAO,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getClienteId(): int { return $this->clienteId; }
    public function getVeiculoId(): int { return $this->veiculoId; }
    public function getStatusAtualId(): int { return $this->statusAtualId; }
    public function getDescricaoProblema(): string { return $this->descricaoProblema; }
    public function getFilial(): string { return $this->filial; }
    public function getStatusAtualNome(): ?string { return $this->statusAtualNome; }
    public function getClienteNome(): ?string { return $this->clienteNome; }
    public function getVeiculoPlaca(): ?string { return $this->veiculoPlaca; }
    public function getCreatedAt(): ?DateTimeInterface { return $this->createdAt; }
    public function getServicos(): ?array { return $this->servicos; }
    public function getHistoricoStatus(): ?array { return $this->historicoStatus; }

    public function setStatusAtualNome(?string $nome): void { $this->statusAtualNome = $nome; }
    public function setClienteNome(?string $nome): void { $this->clienteNome = $nome; }
    public function setVeiculoPlaca(?string $placa): void { $this->veiculoPlaca = $placa; }
    public function setCreatedAt(?DateTimeInterface $dt): void { $this->createdAt = $dt; }
    public function setServicos(?array $servicos): void { $this->servicos = $servicos; }
    public function setHistoricoStatus(?array $historico): void { $this->historicoStatus = $historico; }

    public function alterarStatus(int $novoStatusId): void
    {
        $this->statusAtualId = $novoStatusId;
    }

    /**
     * Lista os insumos consumidos pela OS agregados por insumo, para aplicar
     * a baixa de estoque na aprovação do orçamento.
     *
     * @return array<int, int> insumoId => quantidade total
     */
    public function insumosConsumidos(): array
    {
        $consumo = [];

        /** @var OSServico $servico */
        foreach ($this->servicos ?? [] as $servico) {
            /** @var OSServicoInsumo $insumo */
            foreach ($servico->getInsumos() ?? [] as $insumo) {
                $insumoId = $insumo->getInsumoId();
                $consumo[$insumoId] = ($consumo[$insumoId] ?? 0) + $insumo->getQuantidade();
            }
        }

        return $consumo;
    }
}
