<?php

namespace Domain\Atendimento\Repositories;

use Domain\Atendimento\Entities\OS;

interface OSRepository
{
    public function findById(int $id): ?OS;
    public function findAll(array $filtros = []): array;
    public function save(OS $os): OS;
}
