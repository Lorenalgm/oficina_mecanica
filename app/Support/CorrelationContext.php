<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Guarda o identificador de correlação da requisição em curso.
 *
 * É um holder estático (e não um singleton do container) porque o processor do
 * Monolog é resolvido uma única vez, no boot, e precisa enxergar o valor
 * definido depois, pelo middleware.
 */
class CorrelationContext
{
    private static ?string $correlationId = null;

    public static function set(string $correlationId): void
    {
        self::$correlationId = $correlationId;
    }

    public static function get(): ?string
    {
        return self::$correlationId;
    }

    public static function getOrGenerate(): string
    {
        if (self::$correlationId === null) {
            self::$correlationId = (string) Str::uuid();
        }

        return self::$correlationId;
    }

    public static function clear(): void
    {
        self::$correlationId = null;
    }
}
