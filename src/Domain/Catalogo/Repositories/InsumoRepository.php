<?php

namespace Domain\Catalogo\Repositories;

use Domain\Catalogo\Entities\Insumo;

interface InsumoRepository
{
    public function findById(int $id): ?Insumo;
    public function findAll(): array;
    public function save(Insumo $insumo): Insumo;
    public function delete(int $id): void;
}
