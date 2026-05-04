<?php

namespace Application\Cliente\UseCases;

use Domain\Identidade\Repositories\ClienteRepository;

class RemoverCliente
{
    public function __construct(private ClienteRepository $repositorio) {}

    public function executar(int $id): void
    {
        $this->repositorio->delete($id);
    }
}
