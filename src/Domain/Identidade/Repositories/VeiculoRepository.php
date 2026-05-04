<?php

namespace Domain\Identidade\Repositories;

use Domain\Identidade\Entities\Veiculo;

interface VeiculoRepository
{
    public function findById(int $id): ?Veiculo;
    public function findAll(): array;
    public function save(Veiculo $veiculo): Veiculo;
    public function delete(int $id): void;
}
