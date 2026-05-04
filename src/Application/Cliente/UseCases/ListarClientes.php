<?php

namespace Application\Cliente\UseCases;

use Domain\Identidade\Repositories\ClienteRepository;

class ListarClientes
{
    public function __construct(private ClienteRepository $repositorio) {}

    public function executar(): array
    {
        return $this->repositorio->findAll();
    }
}
