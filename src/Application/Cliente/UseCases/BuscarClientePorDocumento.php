<?php

namespace Application\Cliente\UseCases;

use Domain\Identidade\Entities\Cliente;
use Domain\Identidade\Repositories\ClienteRepository;
use Domain\Shared\ValueObjects\Documento;

class BuscarClientePorDocumento
{
    public function __construct(private ClienteRepository $repositorio) {}

    /**
     * @throws \InvalidArgumentException quando o documento não é um CPF/CNPJ válido
     */
    public function executar(string $documento): ?Cliente
    {
        return $this->repositorio->findByDocumento((new Documento($documento))->getValue());
    }
}
