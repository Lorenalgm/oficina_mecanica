<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O listener EnviarEmailStatusOS é registrado automaticamente pela
        // auto-descoberta de eventos do Laravel 11 (varre app/Listeners pelo
        // type-hint do método handle). Registrá-lo aqui também causaria envio duplicado.
    }
}
