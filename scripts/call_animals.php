<?php
// Script de ayuda para ejecutar una petición HTTP desde el contexto de Laravel
// Uso: php scripts/call_animals.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Boot the application kernel so facades (Http) work
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$token = '10|c8dnhNkO7Ur6kGjXSzhv72WLyppMFSnMAF3evAXQ85289a8d';

try {
    $response = Http::withToken($token)
        ->get('http://127.0.0.1:8000/api/v1/animals');

    // Imprime estado y cuerpo
    echo "HTTP STATUS: " . $response->status() . PHP_EOL;
    echo "RESPONSE BODY:\n" . $response->body() . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . " - " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
