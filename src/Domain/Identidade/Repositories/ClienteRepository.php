<?php

namespace Domain\Identidade\Repositories;

use Domain\Identidade\Entities\Cliente;

interface ClienteRepository
{
    public function findById(int $id): ?Cliente;
    public function findAll(): array;
    public function save(Cliente $cliente): Cliente;
    public function delete(int $id): void;
}
