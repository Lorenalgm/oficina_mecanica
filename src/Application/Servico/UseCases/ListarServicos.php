<?php

namespace Application\Servico\UseCases;

use Domain\Catalogo\Repositories\ServicoRepository;

class ListarServicos
{
    public function __construct(private ServicoRepository $repositorio) {}

    public function executar(): array
    {
        return $this->repositorio->findAll();
    }
}
