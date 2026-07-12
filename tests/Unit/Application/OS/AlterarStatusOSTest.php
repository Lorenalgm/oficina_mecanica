<?php

namespace Tests\Unit\Application\OS;

use Application\OS\UseCases\AlterarStatusOS;
use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Events\StatusOSAlterado;
use Domain\Atendimento\Repositories\OSRepository;
use Domain\Shared\Events\DomainEventDispatcher;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AlterarStatusOSTest extends TestCase
{
    public function test_persiste_status_e_dispara_evento(): void
    {
        $os = new OS(id: 1, clienteId: 10, veiculoId: 20, statusAtualId: 1, descricaoProblema: 'x');

        $repo = Mockery::mock(OSRepository::class);
        $repo->shouldReceive('statusExiste')->once()->with(2)->andReturn(true);
        $repo->shouldReceive('findById')->once()->with(1)->andReturn($os);
        $repo->shouldReceive('registrarStatus')->once()->with(1, 2);

        $dispatcher = Mockery::mock(DomainEventDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn ($evento) => $evento instanceof StatusOSAlterado
                && $evento->statusAnteriorId === 1
                && $evento->statusNovoId === 2
        ));

        (new AlterarStatusOS($repo, $dispatcher))->executar(1, 2);

        $this->assertSame(2, $os->getStatusAtualId());
    }

    public function test_status_inexistente_lanca_excecao(): void
    {
        $repo = Mockery::mock(OSRepository::class);
        $repo->shouldReceive('statusExiste')->once()->with(999)->andReturn(false);
        $repo->shouldNotReceive('registrarStatus');

        $dispatcher = Mockery::mock(DomainEventDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $this->expectException(RuntimeException::class);
        (new AlterarStatusOS($repo, $dispatcher))->executar(1, 999);
    }
}
