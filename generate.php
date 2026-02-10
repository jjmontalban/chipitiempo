<?php

/**
 * ChipiTiempo - Generador de página del tiempo
 *
 * Recopila previsión horaria y alertas, genera HTML ultraligero.
 *
 * Uso:
 *   php generate.php [output_file.html]
 */

require_once __DIR__ . '/src/Alert.php';
require_once __DIR__ . '/src/AEMETForecast.php';
require_once __DIR__ . '/src/AEMETDailyForecast.php';
require_once __DIR__ . '/src/AlertCollector.php';
require_once __DIR__ . '/src/ForecastCollector.php';
require_once __DIR__ . '/src/HtmlBuilder.php';
require_once __DIR__ . '/src/Logging/Logger.php';

use ChipiTiempo\Logging\Logger;

// Cargar variables de entorno
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

// Inicializar logger
Logger::init(__DIR__ . '/logs/generate.log', true);  // File + console
Logger::info("ChipiTiempo generation started");

// Archivo de salida (default: index.html)
$output = $argc > 1 ? $argv[1] : 'index.html';
$originalOutput = $output; // Store original for error messages

try {
    // Recopilar previsiones para múltiples municipios
    Logger::debug("Collecting forecast data");
    $forecasts = ForecastCollector::collectMultiple();

    // Recopilar alertas usando AlertCollector
    Logger::debug("Collecting alert data");
    $alerts = AlertCollector::collect();

    // Generar HTML usando HtmlBuilder
    Logger::debug("Building HTML page");
    $html = HtmlBuilder::buildPage($alerts, $forecasts);
    
    // Asegurar que el directorio existe
    $outputDir = dirname($output);
    if ($outputDir !== '.' && !is_dir($outputDir)) {
        // Intentar crear el directorio con permisos 0755
        $created = @mkdir($outputDir, 0755, true);
        if (!$created && !is_dir($outputDir)) {
            // Si mkdir falló y el directorio aún no existe
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            throw new Exception("Cannot create directory {$outputDir}: {$errorMsg}");
        }
    }
    
    // Resolver el directorio antes de intentar escribir para mensajes de error precisos
    $resolvedDir = $outputDir === '.' ? getcwd() : realpath($outputDir);
    if ($resolvedDir === false) {
        $resolvedDir = $outputDir; // Fallback al path original
    }
    
    // Verificar permisos de escritura ANTES de intentar escribir
    // Solo aplicar fallback si el output especifica un directorio explícito (no solo un nombre de archivo)
    if ($outputDir !== '.' && !is_writable($resolvedDir)) {
        // Intentar escribir en el directorio actual como fallback
        $fallbackOutput = basename($output);
        $currentDir = getcwd();
        
        if (is_writable($currentDir)) {
            echo "[warning] Directory '{$resolvedDir}' is not writable, using current directory: {$currentDir}\n";
            $output = $fallbackOutput;
        } else {
            throw new Exception("Cannot write to {$originalOutput}: directory '{$resolvedDir}' is not writable. Please ensure the directory has write permissions (e.g., chmod 755 for owner-only or chmod 775 for group access) or run the script from a writable directory.");
        }
    }
    
    // Intentar guardar la página
    $bytesWritten = @file_put_contents($output, $html);
    if ($bytesWritten === false) {
        // Capturar el error inmediatamente
        $error = error_get_last();
        $errorMsg = $error ? $error['message'] : 'Unknown error';
        throw new Exception("Cannot write to {$originalOutput}: {$errorMsg}");
    }
    
    $fileSize = filesize($output);
    $forecastCount = 0;
    foreach ($forecasts as $forecast) {
        $forecastCount += count($forecast['hours'] ?? []);
    }
    $alertCount = count($alerts);
    
    $message = "[chipitiempo] {$output} generado ($fileSize bytes, {$forecastCount} horas de previsión, {$alertCount} alertas)";
    Logger::info($message);
    echo $message . "\n";
    
} catch (Exception $exc) {
    $message = "[error] " . $exc->getMessage();
    Logger::error($message);
    echo $message . "\n";
    exit(1);
}
