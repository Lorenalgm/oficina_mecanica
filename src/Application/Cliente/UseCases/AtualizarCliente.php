<?php

namespace Application\Cliente\UseCases;

use Domain\Identidade\Entities\Cliente;
use Domain\Identidade\Repositories\ClienteRepository;

class AtualizarCliente
{
    public function __construct(private ClienteRepository $repositorio) {}

    public function executar(int $id, string $nome, ?string $celular, ?string $email): Cliente
    {
        $cliente = $this->repositorio->findById($id);

        if (!$cliente) {
            throw new \RuntimeException("Cliente #{$id} não encontrado.");
        }

        $cliente->atualizar($nome, $celular, $email);

        return $this->repositorio->save($cliente);
    }
}
