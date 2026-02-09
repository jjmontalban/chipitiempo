<?php

/**
 * ChipiTiempo - Bootstrap: carga de dependencias y configuración
 */

require_once __DIR__ . '/Alert.php';
require_once __DIR__ . '/HourlyForecast.php';
require_once __DIR__ . '/Generator.php';

// Cargar variables de entorno desde .env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}
