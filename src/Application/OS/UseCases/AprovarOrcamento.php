<?php

namespace Application\OS\UseCases;

use DateTimeImmutable;
use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Events\StatusOSAlterado;
use Domain\Atendimento\Exceptions\TokenAprovacaoInvalido;
use Domain\Atendimento\Repositories\OSRepository;
use Domain\Catalogo\Repositories\InsumoRepository;
use Domain\Shared\Events\DomainEventDispatcher;
use RuntimeException;

class AprovarOrcamento
{
    public function __construct(
        private OSRepository $repositorio,
        private InsumoRepository $insumos,
        private DomainEventDispatcher $eventos,
    ) {}

    public function executar(int $osId, ?string $token): OSOrcamento
    {
        $os = $token !== null ? $this->repositorio->findByApprovalToken($token) : null;

        if (!$os || $os->getId() !== $osId) {
            throw new TokenAprovacaoInvalido();
        }

        $orcamento = $os->getOrcamento();
        if (!$orcamento || !$orcamento->isPendente()) {
            throw new RuntimeException('Somente orçamentos pendentes podem ser aprovados.');
        }

        // Valida a baixa de estoque de todos os insumos ANTES de qualquer persistência:
        // se faltar estoque, a exceção interrompe o fluxo sem gravar nada (rollback natural).
        $insumosAtualizados = [];
        foreach ($os->insumosConsumidos() as $insumoId => $quantidade) {
            $insumo = $this->insumos->findById($insumoId);
            if (!$insumo) {
                throw new RuntimeException("Insumo #{$insumoId} não encontrado.");
            }
            $insumo->darBaixa($quantidade);
            $insumosAtualizados[] = $insumo;
        }

        foreach ($insumosAtualizados as $insumo) {
            $this->insumos->save($insumo);
        }

        $orcamento->aprovar(new DateTimeImmutable());
        $this->repositorio->salvarOrcamento($orcamento);

        $statusAnterior = $os->getStatusAtualId();
        $statusId = $this->repositorio->findStatusIdByNome('Em execução');
        if ($statusId !== null) {
            $os->alterarStatus($statusId);
            $this->repositorio->registrarStatus($osId, $statusId);
            $this->eventos->dispatch(new StatusOSAlterado($os, $statusAnterior, $statusId));
        }

        return $orcamento;
    }
}
