<?php

namespace Application\Cliente\UseCases;

use Domain\Identidade\Entities\Cliente;
use Domain\Identidade\Repositories\ClienteRepository;
use Domain\Shared\ValueObjects\Documento;

class CriarCliente
{
    public function __construct(private ClienteRepository $repositorio) {}

    public function executar(string $nome, string $documento, ?string $celular, ?string $email): Cliente
    {
        $cliente = new Cliente(
            id: null,
            nome: $nome,
            documento: new Documento($documento),
            celular: $celular,
            email: $email,
        );

        return $this->repositorio->save($cliente);
    }
}
