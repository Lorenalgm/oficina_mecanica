<?php

use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
Route::get('/consulta-publica', [\App\Http\Controllers\Api\OSController::class, 'consultaPublica']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);

    Route::apiResource('/clientes', \App\Http\Controllers\Api\ClienteController::class);
    Route::apiResource('/veiculos', \App\Http\Controllers\Api\VeiculoController::class);
    Route::apiResource('/servicos', \App\Http\Controllers\Api\ServicoController::class);
    Route::apiResource('/insumos', \App\Http\Controllers\Api\InsumoController::class);

    Route::prefix('/os')->group(function () {
        Route::get('/tempo-medio', [\App\Http\Controllers\Api\OSController::class, 'tempoMedio']);
        Route::get('/', [\App\Http\Controllers\Api\OSController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\OSController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\OSController::class, 'show']);
        Route::patch('/{id}/status', [\App\Http\Controllers\Api\OSController::class, 'alterarStatus']);
        Route::post('/{id}/servicos', [\App\Http\Controllers\Api\OSController::class, 'adicionarServico']);
        Route::post('/{id}/servicos/{osServicoId}/insumos', [\App\Http\Controllers\Api\OSController::class, 'adicionarInsumo']);
        Route::post('/{id}/orcamento', [\App\Http\Controllers\Api\OSController::class, 'gerarOrcamento']);
        Route::post('/{id}/orcamento/aprovar', [\App\Http\Controllers\Api\OSController::class, 'aprovarOrcamento']);
        Route::post('/{id}/orcamento/recusar', [\App\Http\Controllers\Api\OSController::class, 'recusarOrcamento']);
    });
});
