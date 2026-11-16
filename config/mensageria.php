<?php

return [
    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'localhost'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'vhost' => env('RABBITMQ_VHOST', 'oficina'),
        'user' => env('RABBITMQ_USER', 'os'),
        'password' => env('RABBITMQ_PASSWORD', ''),
        'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 0),
        'timeout_confirmacao' => (int) env('RABBITMQ_TIMEOUT_CONFIRMACAO', 5),
        'prefetch' => (int) env('RABBITMQ_PREFETCH', 10),
    ],

    'exchange' => env('MENSAGERIA_EXCHANGE', 'oficina'),
    'exchange_retry' => env('MENSAGERIA_EXCHANGE_RETRY', 'oficina.retry'),
    'fila' => env('MENSAGERIA_FILA', 'os.saga.eventos'),
    'max_tentativas' => (int) env('MENSAGERIA_MAX_TENTATIVAS', 3),

    'outbox' => [
        'lote' => (int) env('OUTBOX_LOTE', 100),
        'intervalo_ms' => (int) env('OUTBOX_INTERVALO_MS', 500),
    ],

    'heartbeat_arquivo' => env('MENSAGERIA_HEARTBEAT', storage_path('framework/mensageria.heartbeat')),

    'saga' => [
        'max_tentativas_compensacao' => (int) env('SAGA_MAX_TENTATIVAS_COMPENSACAO', 3),
    ],

    'handlers' => array_fill_keys(
        Application\Saga\OrquestradorSagaOS::EVENTOS,
        Application\Saga\OrquestradorSagaOS::class,
    ),
];
