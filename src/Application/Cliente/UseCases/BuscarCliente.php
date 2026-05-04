<?php

namespace Application\Cliente\UseCases;

use Domain\Identidade\Entities\Cliente;
use Domain\Identidade\Repositories\ClienteRepository;

class BuscarCliente
{
    public function __construct(private ClienteRepository $repositorio) {}

    public function executar(int $id): ?Cliente
    {
        return $this->repositorio->findById($id);
    }
}
