<?php

namespace Domain\Atendimento\Repositories;

use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Filters\FiltroListagemOS;

interface OSRepository
{
    /** Detalhe completo da OS (cliente, veículo, status, serviços, insumos, histórico, orçamento). */
    public function findById(int $id): ?OS;

    /** Listagem ordenada por prioridade de status, excluindo Finalizada/Entregue. */
    public function findAll(FiltroListagemOS $filtro): array;

    public function save(OS $os): OS;

    public function osExiste(int $osId): bool;

    /** Consulta pública por documento do cliente + placa do veículo. */
    public function findPublica(string $documento, string $placa): ?OS;

    /** Localiza a OS cujo orçamento possui o token de aprovação informado. */
    public function findByApprovalToken(string $token): ?OS;

    public function findStatusIdByNome(string $nome): ?int;

    public function statusExiste(int $statusId): bool;

    /** Atualiza o status atual da OS e registra o histórico (os_status), atomicamente. */
    public function registrarStatus(int $osId, int $statusId): void;

    public function servicoExiste(int $servicoId): bool;

    public function adicionarServico(int $osId, int $servicoId): OSServico;

    public function osServicoExiste(int $osServicoId): bool;

    public function insumoExiste(int $insumoId): bool;

    public function adicionarInsumo(int $osServicoId, int $insumoId, int $quantidade): OSServicoInsumo;

    /** Cria o orçamento gerando um approval_token único, e o retorna (com o token). */
    public function criarOrcamento(int $osId, float $valorTotal): OSOrcamento;

    /** Persiste alterações de um orçamento existente (status, data de aprovação, token). */
    public function salvarOrcamento(OSOrcamento $orcamento): void;

    /** Tempo médio (em minutos) entre "Em execução" e "Finalizada". */
    public function calcularTempoMedioMinutos(): ?float;
}
