<?php

namespace Domain\Atendimento\Saga;

interface SagaRepository
{
    public function salvar(SagaOS $saga): void;

    public function buscar(string $id): ?SagaOS;

    public function buscarAtivaDaOS(int $osId): ?SagaOS;

    public function buscarUltimaDaOS(int $osId): ?SagaOS;

    public function registrarPasso(string $sagaId, string $passo, array $detalhes = []): void;

    /** @return list<array{passo: string, detalhes: array, registrado_em: \DateTimeInterface}> */
    public function passos(string $sagaId): array;
}
