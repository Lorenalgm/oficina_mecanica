<?php

namespace App\Providers;

use Domain\Catalogo\Repositories\InsumoRepository;
use Domain\Catalogo\Repositories\ServicoRepository;
use Domain\Identidade\Repositories\ClienteRepository;
use Domain\Identidade\Repositories\VeiculoRepository;
use Domain\Atendimento\Repositories\OSRepository;
use Infrastructure\Persistence\Eloquent\EloquentInsumoRepository;
use Infrastructure\Persistence\Eloquent\EloquentServicoRepository;
use Infrastructure\Persistence\Eloquent\EloquentClienteRepository;
use Infrastructure\Persistence\Eloquent\EloquentVeiculoRepository;
use Infrastructure\Persistence\Eloquent\EloquentOSRepository;
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
    }
}
