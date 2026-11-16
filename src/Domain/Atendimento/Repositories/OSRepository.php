<?php

namespace Domain\Atendimento\Repositories;

use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Filters\FiltroListagemOS;

interface OSRepository
{
    /** Detalhe completo da OS (cliente, veículo, status, serviços, insumos, histórico). */
    public function findById(int $id): ?OS;

    /** Listagem ordenada por prioridade de status, excluindo Finalizada/Entregue. */
    public function findAll(FiltroListagemOS $filtro): array;

    public function save(OS $os): OS;

    public function osExiste(int $osId): bool;

    /** Consulta pública por documento do cliente + placa do veículo. */
    public function findPublica(string $documento, string $placa): ?OS;

    public function findStatusIdByNome(string $nome): ?int;

    public function statusExiste(int $statusId): bool;

    /** Atualiza o status atual da OS e registra o histórico (os_status), atomicamente. */
    public function registrarStatus(int $osId, int $statusId): void;

    public function servicoExiste(int $servicoId): bool;

    public function adicionarServico(int $osId, int $servicoId): OSServico;

    public function osServicoExiste(int $osServicoId): bool;

    public function insumoExiste(int $insumoId): bool;

    public function adicionarInsumo(int $osServicoId, int $insumoId, int $quantidade): OSServicoInsumo;

    /** Tempo médio (em minutos) entre "Em execução" e "Finalizada". */
    public function calcularTempoMedioMinutos(): ?float;
}
