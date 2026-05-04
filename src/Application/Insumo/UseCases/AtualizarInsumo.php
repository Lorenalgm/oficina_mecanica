<?php

namespace Application\Insumo\UseCases;

use Domain\Catalogo\Entities\Insumo;
use Domain\Catalogo\Repositories\InsumoRepository;

class AtualizarInsumo
{
    public function __construct(private InsumoRepository $repositorio) {}

    public function executar(int $id, string $nome, float $valor, int $quantidadeEstoque): Insumo
    {
        $insumo = $this->repositorio->findById($id);

        if (!$insumo) {
            throw new \RuntimeException("Insumo #{$id} não encontrado.");
        }

        $insumo->atualizar($nome, $valor, $quantidadeEstoque);

        return $this->repositorio->save($insumo);
    }
}
