<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Repositories\OSRepository;
use RuntimeException;

class AdicionarInsumoNoServico
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $osServicoId, int $insumoId, int $quantidade): OSServicoInsumo
    {
        if (!$this->repositorio->osServicoExiste($osServicoId)) {
            throw new RuntimeException("OSServico #{$osServicoId} não encontrado.");
        }

        if (!$this->repositorio->insumoExiste($insumoId)) {
            throw new RuntimeException("Insumo #{$insumoId} não encontrado.");
        }

        return $this->repositorio->adicionarInsumo($osServicoId, $insumoId, $quantidade);
    }
}
