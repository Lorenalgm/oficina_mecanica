<?php

namespace Domain\Catalogo\Repositories;

use Domain\Catalogo\Entities\Servico;

interface ServicoRepository
{
    public function findById(int $id): ?Servico;
    public function findAll(): array;
    public function save(Servico $servico): Servico;
    public function delete(int $id): void;
}
