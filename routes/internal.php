<?php

use App\Http\Controllers\Api\InternoController;
use Illuminate\Support\Facades\Route;

Route::get('/clientes/por-documento/{documento}', [InternoController::class, 'clientePorDocumento']);
