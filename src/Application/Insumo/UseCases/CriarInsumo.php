<?php

namespace Application\Insumo\UseCases;

use Domain\Catalogo\Entities\Insumo;
use Domain\Catalogo\Repositories\InsumoRepository;

class CriarInsumo
{
    public function __construct(private InsumoRepository $repositorio) {}

    public function executar(string $nome, float $valor, int $quantidadeEstoque): Insumo
    {
        $insumo = new Insumo(id: null, nome: $nome, valor: $valor, quantidadeEstoque: $quantidadeEstoque);

        return $this->repositorio->save($insumo);
    }
}
