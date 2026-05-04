<?php

namespace Application\Servico\UseCases;

use Domain\Catalogo\Entities\Servico;
use Domain\Catalogo\Repositories\ServicoRepository;

class BuscarServico
{
    public function __construct(private ServicoRepository $repositorio) {}

    public function executar(int $id): ?Servico
    {
        return $this->repositorio->findById($id);
    }
}
