<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fase 2+: bindings de interfaces → Eloquent repositories serão registrados aqui
        // Ex: $this->app->bind(ClienteRepository::class, EloquentClienteRepository::class);
    }
}
