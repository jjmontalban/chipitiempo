<?php

/**
 * ChipiTiempo - Fuente AEMET: previsión horaria y diaria
 */

namespace ChipiTiempo\Sources;

require_once __DIR__ . '/../AEMETForecast.php';
require_once __DIR__ . '/../AEMETDailyForecast.php';
require_once __DIR__ . '/../Cache.php';

use ChipiTiempo\AEMETForecast;
use ChipiTiempo\AEMETDailyForecast;
use ChipiTiempo\Cache;

class AEMET {
    private const BASE_URL = "https://opendata.aemet.es/opendata";
    private const HOURLY_ENDPOINT = "/api/prediccion/especifica/municipio/horaria/{municipio}";
    private const DAILY_ENDPOINT = "/api/prediccion/especifica/municipio/diaria/{municipio}";
    private const CACHE_TTL = 300; // 5 minutos
    
    // Configuración de reintentos para peticiones HTTP
    private const MAX_RETRIES = 3;
    private const INITIAL_RETRY_DELAY = 1; // segundos

    private static function getApiKey(): string {
        return $_ENV['AEMET_API_KEY'] ?? getenv('AEMET_API_KEY') ?? '';
    }

    /**
     * Obtener instancia del cache
     */
    private static function getCache(): Cache {
        static $cache = null;
        if ($cache === null) {
            $cacheDir = sys_get_temp_dir() . '/chipitiempo';
            $cache = new Cache($cacheDir, self::CACHE_TTL);
        }
        return $cache;
    }

    /**
     * Hacer solicitud HTTP GET con clave API, con reintentos para errores transitorios
     */
    private static function request(string $url, string $accept = "application/json"): string {
        $maxRetries = self::MAX_RETRIES;
        $retryDelay = self::INITIAL_RETRY_DELAY;
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => implode("\r\n", [
                            "Accept: {$accept}",
                            "api_key: " . self::getApiKey(),
                            "User-Agent: AUXIO/1.0",
                        ]),
                        'timeout' => 30,
                        'ignore_errors' => true, // Allow capturing HTTP error responses
                    ]
                ]);

                $response = @file_get_contents($url, false, $context);
                if ($response === false) {
                    $error = error_get_last();
                    throw new \Exception("Error fetching {$url}: " . ($error['message'] ?? 'Unknown error'));
                }
                
                // Verificar código de respuesta HTTP
                if (isset($http_response_header)) {
                    $statusLine = $http_response_header[0] ?? '';
                    preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
                    $statusCode = $matches[1] ?? 0;
                    
                    // 429 (Too Many Requests) y 503 (Service Unavailable) son transitorios, reintentar
                    if ($statusCode == 429 || $statusCode == 503) {
                        throw new \Exception("HTTP {$statusCode} error (transient) for {$url}");
                    }
                    
                    if ($statusCode >= 400) {
                        throw new \Exception("HTTP {$statusCode} error for {$url}");
                    }
                }
                
                // Fijar encoding: detectar e intentar convertir a UTF-8 si es necesario
                // AEMET a veces devuelve Latin-1 a pesar de decir que es UTF-8
                if (!mb_check_encoding($response, 'UTF-8')) {
                    $response = mb_convert_encoding($response, 'UTF-8', 'ISO-8859-1,UTF-8,ASCII');
                }
                
                return $response;
                
            } catch (\Exception $e) {
                $lastException = $e;
                
                // Si no es el último intento y es un error transitorio, esperar y reintentar
                if ($attempt < $maxRetries) {
                    $errorMsg = $e->getMessage();
                    // Reintentar en errores de red o HTTP 429/503
                    if (str_contains($errorMsg, 'getaddrinfo') || 
                        str_contains($errorMsg, 'Connection refused') ||
                        str_contains($errorMsg, 'Connection timed out') ||
                        str_contains($errorMsg, 'transient')) {
                        echo "[aemet] Attempt {$attempt} failed: {$errorMsg}. Retrying in {$retryDelay}s...\n";
                        sleep($retryDelay);
                        $retryDelay *= 2; // Exponential backoff
                        continue;
                    }
                }
                
                // Si no es transitorio o es el último intento, lanzar la excepción
                throw $e;
            }
        }
        
        // Si llegamos aquí, todos los reintentos fallaron
        throw $lastException ?? new \Exception("Request failed after {$maxRetries} attempts");
    }

    /**
     * Obtener la URL de datos para previsión horaria de un municipio
     */
    private static function getHourlyDataUrl(string $municipioId): string {
        $url = self::BASE_URL . str_replace("{municipio}", $municipioId, self::HOURLY_ENDPOINT);
        $body = json_decode(self::request($url), true);

        if (!isset($body['datos'])) {
            throw new \Exception("AEMET hourly response missing 'datos' field");
        }
        return $body['datos'];
    }

    /**
     * Obtener previsión horaria para un municipio
     *
     * @param string $municipioId Código INE del municipio (ej: "11016" para Chipiona)
     * @return array{name: string, province: string, issued: string, hours: HourlyForecast[]}
     */
    public static function fetchHourlyForecast(string $municipioId = '11016'): array {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            echo "[aemet] AEMET_API_KEY not set, skipping hourly forecast.\n";
            return ['name' => '', 'province' => '', 'issued' => '', 'hours' => []];
        }

        // Verificar cache antes de hacer solicitud a AEMET
        $cacheKey = "hourly_{$municipioId}";
        $cached = self::getCache()->getIfValid($cacheKey);
        if ($cached !== null) {
            echo "[aemet] Cache hit for hourly forecast {$municipioId}\n";
            return $cached;
        }

        try {
            $datosUrl = self::getHourlyDataUrl($municipioId);
            $rawData = self::request($datosUrl);

            $json = json_decode($rawData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON: " . json_last_error_msg() . " - raw: " . substr($rawData, 0, 200));
            }
            if (!is_array($json) || empty($json)) {
                throw new \Exception("Empty or non-array JSON response");
            }

            $data = $json[0] ?? [];
            $name = $data['nombre'] ?? '';
            $province = $data['provincia'] ?? '';
            $issued = $data['elaborado'] ?? '';
            $days = $data['prediccion']['dia'] ?? [];
            echo "[aemet] Forecast for {$name} ({$province}), issued: {$issued}, days: " . count($days) . "\n";

            $hours = [];
            foreach ($days as $day) {
                $fecha = $day['fecha'] ?? '';
                // Extraer la fecha base (YYYY-MM-DD)
                $dateBase = substr($fecha, 0, 10);

                // Indexar temperatura por periodo
                $temps = self::indexByPeriod($day['temperatura'] ?? []);
                $feels = self::indexByPeriod($day['sensTermica'] ?? []);
                $humidity = self::indexByPeriod($day['humedadRelativa'] ?? []);
                $precip = self::indexByPeriod($day['precipitacion'] ?? []);
                $sky = self::indexByPeriod($day['estadoCielo'] ?? []);

                // Probabilidad de precipitación (puede venir en periodos de 6h)
                $precipProb = self::indexPrecipProb($day['probPrecipitacion'] ?? []);

                // Viento y rachas máximas: la API horaria devuelve el campo combinado "vientoAndRachaMax"
                $windAndGust = self::indexWindAndGust($day['vientoAndRachaMax'] ?? []);
                $wind = $windAndGust['wind'];
                $gusts = $windAndGust['gusts'];

                // Generar un AEMETForecast por cada hora que tenga temperatura
                foreach ($temps as $periodo => $tempVal) {
                    $hour = str_pad($periodo, 2, '0', STR_PAD_LEFT);
                    $datetime = "{$dateBase}T{$hour}:00:00";

                    $skyEntry = $sky[$periodo] ?? null;

                    $periodInt = (int)$periodo;
                    $hours[] = new AEMETForecast(
                        datetime: $datetime,
                        temperature: $tempVal !== null ? (int)$tempVal : null,
                        feelsLike: isset($feels[$periodo]) ? (int)$feels[$periodo] : null,
                        humidity: isset($humidity[$periodo]) ? (int)$humidity[$periodo] : null,
                        precipProb: $precipProb[$periodInt] ?? null,
                        precipAmount: $precip[$periodo] ?? null,
                        windDir: $wind[$periodInt]['dir'] ?? null,
                        windSpeed: $wind[$periodInt]['speed'] ?? null,
                        windGust: $gusts[$periodInt] ?? null,
                        skyDescription: is_array($skyEntry) ? ($skyEntry['descripcion'] ?? null) : null,
                        skyCode: is_array($skyEntry) ? ($skyEntry['value'] ?? null) : (($skyEntry !== null) ? (string)$skyEntry : null),
                    );
                }
            }

            $result = [
                'name' => $name,
                'province' => $province,
                'issued' => $issued,
                'hours' => $hours,
            ];
            
            // Guardar en cache
            self::getCache()->set($cacheKey, $result);
            
            return $result;
        } catch (\Throwable $exc) {
            echo "[aemet] Error fetching hourly forecast: {$exc->getMessage()}\n";
            
            // Intentar usar cache como fallback (get() no verifica expiración)
            $staleCache = self::getCache()->get($cacheKey);
            if ($staleCache !== null && !empty($staleCache['hours'])) {
                echo "[aemet] Using stale cache as fallback for hourly forecast {$municipioId}\n";
                return $staleCache;
            }
            
            return ['name' => '', 'province' => '', 'issued' => '', 'hours' => []];
        }
    }

    /**
     * Indexar array de datos por periodo (campo "periodo" → campo "value")
     */
    private static function indexByPeriod(array $entries): array {
        $indexed = [];
        foreach ($entries as $entry) {
            $periodo = $entry['periodo'] ?? null;
            if ($periodo === null || $periodo === '') continue;
            // Para estadoCielo, guardar el objeto completo (tiene descripcion)
            if (isset($entry['descripcion'])) {
                $indexed[$periodo] = $entry;
            } else {
                $val = $entry['value'] ?? null;
                $indexed[$periodo] = ($val !== null && $val !== '' && $val !== 'Ip') ? $val : '0';
            }
        }
        return $indexed;
    }

    /**
     * Indexar probabilidad de precipitación expandiendo rangos a horas individuales
     * Soporta periodos: "00" (individual), "0006" (concatenado), "00-06" (con guion)
     */
    private static function indexPrecipProb(array $entries): array {
        $indexed = [];
        foreach ($entries as $entry) {
            $periodo = $entry['periodo'] ?? '';
            $val = $entry['value'] ?? null;
            $prob = ($val !== null && $val !== '') ? (int)$val : 0;

            if (strpos($periodo, '-') !== false) {
                // Rango con guion (ej: "00-06", "06-12")
                $parts = explode('-', $periodo);
                $start = (int)$parts[0];
                $end = (int)$parts[1];
                for ($h = $start; $h < $end; $h++) {
                    $indexed[$h] = $prob;
                }
            } elseif (strlen($periodo) > 2) {
                // Rango concatenado (ej: "0006", "0612")
                $start = (int)substr($periodo, 0, 2);
                $end = (int)substr($periodo, 2, 2);
                for ($h = $start; $h < $end; $h++) {
                    $indexed[$h] = $prob;
                }
            } else {
                // Periodo individual (ej: "00", "01")
                $indexed[(int)$periodo] = $prob;
            }
        }
        return $indexed;
    }

    /**
     * Extraer valor escalar de un campo que AEMET puede devolver como array o string.
     * Ej: ["SO"] → "SO", "SO" → "SO", ["10"] → "10"
     */
    private static function extractScalar(mixed $value): string {
        if (is_array($value)) {
            return (string)($value[0] ?? '');
        }
        return (string)$value;
    }

    /**
     * Indexar viento desde campo "viento" (direccion, velocidad, periodo)
     * Soporta periodos: "00" (individual), "0006" (concatenado), "00-06" (con guion)
     * AEMET puede devolver "direccion" y "velocidad" como arrays (ej: ["SO"]) o strings.
     * AEMET a veces devuelve un único objeto en lugar de un array de objetos.
     */
    private static function indexWind(array $entries): array {
        // Si AEMET devuelve un único objeto (sin índice numérico), envolverlo en un array
        if (!isset($entries[0]) && !empty($entries)) {
            $entries = [$entries];
        }
        $indexed = [];
        foreach ($entries as $entry) {
            $periodo = $entry['periodo'] ?? '';
            if ($periodo === '') continue;
            $dir = self::extractScalar($entry['direccion'] ?? '');
            $speed = self::extractScalar($entry['velocidad'] ?? '');
            if ($dir === '' && $speed === '') continue;

            $windVal = [
                'dir' => $dir !== '' ? $dir : null,
                'speed' => $speed !== '' ? (int)$speed : null,
            ];

            if (strpos($periodo, '-') !== false) {
                // Rango con guion (ej: "00-06", "06-12")
                $parts = explode('-', $periodo);
                $start = (int)$parts[0];
                $end = (int)$parts[1];
                for ($h = $start; $h < $end; $h++) {
                    $indexed[$h] = $windVal;
                }
            } elseif (strlen($periodo) > 2) {
                // Rango concatenado (ej: "0006", "0612")
                $start = (int)substr($periodo, 0, 2);
                $end = (int)substr($periodo, 2, 2);
                for ($h = $start; $h < $end; $h++) {
                    $indexed[$h] = $windVal;
                }
            } else {
                // Periodo individual (ej: "00", "01")
                $indexed[(int)$periodo] = $windVal;
            }
        }
        return $indexed;
    }

    /**
     * Indexar viento y rachas desde campo "vientoAndRachaMax" (formato previsión horaria)
     * Cada entrada tiene: direccion, velocidad, rachaMax, periodo
     * Devuelve ['wind' => [...], 'gusts' => [...]] indexados por hora entera
     * AEMET puede devolver los valores como arrays (ej: ["SO"]) o strings.
     * AEMET a veces devuelve un único objeto en lugar de un array de objetos.
     */
    private static function indexWindAndGust(array $entries): array {
        // Si AEMET devuelve un único objeto (sin índice numérico), envolverlo en un array
        if (!isset($entries[0]) && !empty($entries)) {
            $entries = [$entries];
        }
        $windIndexed = [];
        $gustIndexed = [];
        foreach ($entries as $entry) {
            $periodo = $entry['periodo'] ?? '';
            if ($periodo === '') continue;
            $dir = self::extractScalar($entry['direccion'] ?? '');
            $speed = self::extractScalar($entry['velocidad'] ?? '');
            $gust = self::extractScalar($entry['rachaMax'] ?? '');

            $windVal = [
                'dir' => $dir !== '' ? $dir : null,
                'speed' => $speed !== '' ? (int)$speed : null,
            ];
            $gustVal = ($gust !== '' && $gust !== 'Ip') ? (int)$gust : null; // 'Ip' = Imperceptible (valor inapreciable)

            if (strpos($periodo, '-') !== false) {
                // Rango con guion (ej: "00-06", "06-12")
                $parts = explode('-', $periodo);
                $start = (int)$parts[0];
                $end = (int)$parts[1];
                for ($h = $start; $h < $end; $h++) {
                    if ($dir !== '' || $speed !== '') $windIndexed[$h] = $windVal;
                    if ($gustVal !== null) $gustIndexed[$h] = $gustVal;
                }
            } elseif (strlen($periodo) === 4) {
                // Rango concatenado (ej: "0006", "0612")
                $start = (int)substr($periodo, 0, 2);
                $end = (int)substr($periodo, 2, 2);
                for ($h = $start; $h < $end; $h++) {
                    if ($dir !== '' || $speed !== '') $windIndexed[$h] = $windVal;
                    if ($gustVal !== null) $gustIndexed[$h] = $gustVal;
                }
            } else {
                // Periodo individual (ej: "00", "01")
                $h = (int)$periodo;
                if ($dir !== '' || $speed !== '') $windIndexed[$h] = $windVal;
                if ($gustVal !== null) $gustIndexed[$h] = $gustVal;
            }
        }
        return ['wind' => $windIndexed, 'gusts' => $gustIndexed];
    }

    /**
     * Indexar rachas máximas desde campo "rachaMax" (value, periodo)
     * El periodo puede ser individual ("00"), rango concatenado ("0006") o rango con guion ("00-06")
     * AEMET a veces devuelve un único objeto en lugar de un array de objetos.
     */
    private static function indexGust(array $entries): array {
        // Si AEMET devuelve un único objeto (sin índice numérico), envolverlo en un array
        if (!isset($entries[0]) && !empty($entries)) {
            $entries = [$entries];
        }
        $indexed = [];
        foreach ($entries as $entry) {
            $periodo = $entry['periodo'] ?? '';
            $val = $entry['value'] ?? '';
            if ($val === '' || $periodo === '') continue;
            $gust = (int)$val;

            if (strpos($periodo, '-') !== false) {
                // Rango con guion (ej: "00-06", "06-12")
                $parts = explode('-', $periodo);
                $start = (int)$parts[0];
                $end = (int)$parts[1];
                for ($h = $start; $h < $end; $h++) {
                    $indexed[$h] = $gust;
                }
            } elseif (strlen($periodo) > 2) {
                // Rango concatenado (ej: "0006", "0612")
                $start = (int)substr($periodo, 0, 2);
                $end = (int)substr($periodo, 2, 2);
                for ($h = $start; $h < $end; $h++) {
                    $indexed[$h] = $gust;
                }
            } else {
                $indexed[(int)$periodo] = $gust;
            }
        }
        return $indexed;
    }


    /**
     * Obtener previsión diaria para un municipio (5-7 días)
     */
    public static function fetchDailyForecast(string $municipioId = '11016'): array {
        // Verificar que tenemos API key
        if (!self::getApiKey()) {
            echo "[aemet] AEMET_API_KEY not set, skipping daily forecast.\n";
            return ['name' => '', 'province' => '', 'issued' => '', 'days' => []];
        }

        $cacheKey = "daily_{$municipioId}";
        $cached = self::getCache()->getIfValid($cacheKey);
        if ($cached !== null) {
            echo "[aemet] Cache hit for daily forecast {$municipioId}\n";
            return $cached;
        }

        try {
            // Obtener datos del endpoint diario
            $datosUrl = self::BASE_URL . str_replace("{municipio}", $municipioId, self::DAILY_ENDPOINT);
            $body = json_decode(self::request($datosUrl), true);
            
            if (!isset($body['datos'])) {
                throw new \Exception("AEMET daily response missing 'datos' field");
            }

            $datosUrl = $body['datos']; // La respuesta contiene una URL a los datos comprimidos
            $datosBody = json_decode(self::request($datosUrl), true);

            if (!is_array($datosBody) || empty($datosBody)) {
                throw new \Exception("Invalid daily forecast JSON");
            }

            // El endpoint diario, igual que el horario, devuelve un array
            $data = $datosBody[0] ?? [];

            // Parsear datos de previsión diaria
            $name = $data['nombre'] ?? '';
            $province = $data['provincia'] ?? '';
            $issued = $data['elaboracion'] ?? '';
            $days = [];

            if (!isset($data['prediccion']['dia'])) {
                throw new \Exception("No daily forecast data found");
            }

            foreach ($data['prediccion']['dia'] as $dayData) {
                $date = $dayData['fecha'] ?? null;
                if (!$date) continue;

                $tempMin = null;
                $tempMax = null;

                // Buscar temperaturas en los períodos del día
                if (isset($dayData['temperatura'])) {
                    $tempMin = isset($dayData['temperatura']['minima']) ? (int)$dayData['temperatura']['minima'] : null;
                    $tempMax = isset($dayData['temperatura']['maxima']) ? (int)$dayData['temperatura']['maxima'] : null;
                }

                // Estado del cielo
                $skyDescription = '';
                $skyCode = null;
                if (isset($dayData['estadoCielo']) && is_array($dayData['estadoCielo'])) {
                    foreach ($dayData['estadoCielo'] as $skyEntry) {
                        if (isset($skyEntry['descripcion'])) {
                            $skyDescription = $skyEntry['descripcion'];
                            $skyCode = $skyEntry['value'] ?? null;
                            break;
                        }
                    }
                } else if (isset($dayData['estadoCielo']['descripcion'])) {
                    $skyDescription = $dayData['estadoCielo']['descripcion'];
                    $skyCode = $dayData['estadoCielo']['value'] ?? null;
                }

                // Probabilidad de precipitación
                $precipProb = null;
                if (isset($dayData['probPrecipitacion']) && is_array($dayData['probPrecipitacion'])) {
                    foreach ($dayData['probPrecipitacion'] as $p) {
                        if (isset($p['valor'])) {
                            $precipProb = (int)$p['valor'];
                            break;
                        }
                    }
                } else if (isset($dayData['probPrecipitacion'])) {
                    $precipProb = (int)$dayData['probPrecipitacion'];
                }

                // Viento
                // La API AEMET devuelve 'viento' como array de entradas (ej: [{"direccion":"SO","velocidad":"20","periodo":"0024"}])
                $windDir = null;
                $windSpeed = null;
                if (isset($dayData['viento'])) {
                    $vientoEntries = (is_array($dayData['viento']) && isset($dayData['viento'][0]))
                        ? $dayData['viento']
                        : [$dayData['viento']];
                    foreach ($vientoEntries as $vEntry) {
                        if (!is_array($vEntry)) continue;
                        $dir = self::extractScalar($vEntry['direccion'] ?? '');
                        $speed = self::extractScalar($vEntry['velocidad'] ?? '');
                        if ($dir !== '' || $speed !== '') {
                            $windDir = $dir !== '' ? $dir : null;
                            $windSpeed = $speed !== '' ? (int)$speed : null;
                            break;
                        }
                    }
                }

                $days[] = new AEMETDailyForecast(
                    date: $date,
                    tempMin: $tempMin,
                    tempMax: $tempMax,
                    skyDescription: $skyDescription ?: null,
                    precipProb: $precipProb,
                    windDir: $windDir,
                    windSpeed: $windSpeed
                );
            }

            $result = [
                'name' => $name,
                'province' => $province,
                'issued' => $issued,
                'days' => $days,
            ];
            self::getCache()->set($cacheKey, $result);
            return $result;
        } catch (\Exception $exc) {
            echo "[aemet] Error fetching daily forecast: {$exc->getMessage()}\n";
            
            // Intentar usar cache como fallback (get() no verifica expiración)
            $staleCache = self::getCache()->get($cacheKey);
            if ($staleCache !== null && !empty($staleCache['days'])) {
                echo "[aemet] Using stale cache as fallback for daily forecast {$municipioId}\n";
                return $staleCache;
            }
            
            return ['name' => '', 'province' => '', 'issued' => '', 'days' => []];
        }
    }
}
