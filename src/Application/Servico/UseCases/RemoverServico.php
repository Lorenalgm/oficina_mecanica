<?php

namespace Application\Servico\UseCases;

use Domain\Catalogo\Repositories\ServicoRepository;

class RemoverServico
{
    public function __construct(private ServicoRepository $repositorio) {}

    public function executar(int $id): void
    {
        $this->repositorio->delete($id);
    }
}
