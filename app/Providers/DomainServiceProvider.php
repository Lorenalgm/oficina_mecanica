<?php

namespace App\Providers;

use Domain\Catalogo\Repositories\InsumoRepository;
use Domain\Catalogo\Repositories\ServicoRepository;
use Domain\Identidade\Repositories\ClienteRepository;
use Domain\Identidade\Repositories\VeiculoRepository;
use Domain\Atendimento\Repositories\OSRepository;
use Application\Saga\OrquestradorSagaOS;
use Domain\Atendimento\Saga\NotificadorCliente;
use Domain\Atendimento\Saga\SagaRepository;
use Domain\Shared\Events\DomainEventDispatcher;
use Domain\Shared\Transacao;
use Infrastructure\Events\LaravelEventDispatcher;
use Infrastructure\Notificacao\EmailNotificadorCliente;
use Infrastructure\Persistence\LaravelTransacao;
use Infrastructure\Persistence\Eloquent\EloquentInsumoRepository;
use Infrastructure\Persistence\Eloquent\EloquentServicoRepository;
use Infrastructure\Persistence\Eloquent\EloquentClienteRepository;
use Infrastructure\Persistence\Eloquent\EloquentVeiculoRepository;
use Infrastructure\Persistence\Eloquent\EloquentOSRepository;
use Infrastructure\Persistence\Eloquent\EloquentSagaRepository;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServicoRepository::class, EloquentServicoRepository::class);
        $this->app->bind(InsumoRepository::class, EloquentInsumoRepository::class);
        $this->app->bind(ClienteRepository::class, EloquentClienteRepository::class);
        $this->app->bind(VeiculoRepository::class, EloquentVeiculoRepository::class);
        $this->app->bind(OSRepository::class, EloquentOSRepository::class);
        $this->app->bind(SagaRepository::class, EloquentSagaRepository::class);
        $this->app->bind(DomainEventDispatcher::class, LaravelEventDispatcher::class);
        $this->app->bind(Transacao::class, LaravelTransacao::class);
        $this->app->bind(NotificadorCliente::class, EmailNotificadorCliente::class);
        $this->app->when(OrquestradorSagaOS::class)
            ->needs('$maxTentativasCompensacao')
            ->giveConfig('mensageria.saga.max_tentativas_compensacao', 3);
    }
}
