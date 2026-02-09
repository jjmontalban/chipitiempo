<?php

/**
 * ChipiTiempo - Fuente AEMET: avisos meteorológicos y previsión horaria
 */

require_once __DIR__ . '/../Alert.php';
require_once __DIR__ . '/../HourlyForecast.php';

class AEMETSource {
    private const API_KEY = null;  // Se lee de ENV
    private const BASE_URL = "https://opendata.aemet.es/opendata";
    private const ENDPOINT = "/api/avisos_cap/ultimoelaborado/area/{area}";
    private const HOURLY_ENDPOINT = "/api/prediccion/especifica/municipio/horaria/{municipio}";
    private const CAP_NS = "urn:oasis:names:tc:emergency:cap:1.2";

    private const SEVERITY_MAP = [
        "Extreme" => "red",
        "Severe" => "orange",
        "Moderate" => "yellow",
        "Minor" => "green",
        "Unknown" => "yellow",
    ];

    private static function getApiKey(): string {
        return $_ENV['AEMET_API_KEY'] ?? getenv('AEMET_API_KEY') ?? '';
    }

    /**
     * Hacer solicitud HTTP GET con clave API
     */
    private static function request(string $url, string $accept = "application/json"): string {
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
            throw new Exception("Error fetching {$url}: " . ($error['message'] ?? 'Unknown error'));
        }
        
        // Verificar código de respuesta HTTP
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
            $statusCode = $matches[1] ?? 0;
            
            if ($statusCode >= 400) {
                throw new Exception("HTTP {$statusCode} error for {$url}");
            }
        }
        
        return $response;
    }

    /**
     * Paso 1: obtener la URL de datos del endpoint AEMET
     */
    private static function getDataUrl(string $area = "esp"): string {
        $url = self::BASE_URL . str_replace("{area}", $area, self::ENDPOINT);
        $body = json_decode(self::request($url), true);

        if (!isset($body['datos'])) {
            throw new Exception("AEMET response missing 'datos' field");
        }
        return $body['datos'];
    }

    /**
     * Extraer archivos XML de un archivo tar (comprimido o no)
     */
    private static function extractTar(string $data, bool $gzipped): array {
        $xmlFiles = [];
        $tmpFile = tempnam(sys_get_temp_dir(), 'aemet_');
        $tmpDir = $tmpFile . '_dir';

        try {
            file_put_contents($tmpFile, $data);
            mkdir($tmpDir, 0755, true);

            // Use PharData for cross-platform tar extraction (works on Windows too)
            try {
                $phar = new PharData($tmpFile);
                $phar->extractTo($tmpDir);
            } catch (Exception $e) {
                throw new Exception("Failed to extract tar archive: " . $e->getMessage());
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                    $xmlFiles[] = file_get_contents($file->getPathname());
                }
            }
        } finally {
            @unlink($tmpFile);
            if (is_dir($tmpDir)) {
                $delIterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($delIterator as $item) {
                    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                }
                @rmdir($tmpDir);
            }
        }

        return $xmlFiles;
    }

    /**
     * Obtener texto de un elemento XML
     */
    private static function getText(SimpleXMLElement $parent, string $tag): ?string {
        $element = $parent->children(self::CAP_NS);
        
        if (isset($element->{$tag})) {
            $text = (string)$element->{$tag};
            return !empty($text) ? trim($text) : null;
        }
        
        if (isset($parent->{$tag})) {
            $text = (string)$parent->{$tag};
            return !empty($text) ? trim($text) : null;
        }
        
        return null;
    }

    /**
     * Seleccionar bloque <info> en español
     */
    private static function pickSpanish(array $infos): ?SimpleXMLElement {
        foreach ($infos as $info) {
            $lang = self::getText($info, 'language') ?? '';
            if (stripos($lang, 'es') === 0) {
                return $info;
            }
        }
        return null;
    }

    /**
     * Parsear CAP 1.2 XML en Alerts
     */
    private static function parseCAP(string $xml): array {
        $alerts = [];
        
        try {
            $root = new SimpleXMLElement($xml);
        } catch (Exception $e) {
            throw new Exception("Invalid CAP XML: {$e->getMessage()}");
        }

        $alertElements = [];

        // Si la raíz es <alert>, procesarla directamente
        if ($root->getName() === 'alert') {
            $alertElements[] = $root;
        } else {
            // Buscar elementos <alert> hijos (con y sin namespace)
            $children = $root->children(self::CAP_NS);
            if (isset($children->alert)) {
                foreach ($children->alert as $alert) {
                    $alertElements[] = $alert;
                }
            }

            if (empty($alertElements) && isset($root->alert)) {
                foreach ($root->alert as $alert) {
                    $alertElements[] = $alert;
                }
            }
        }

        foreach ($alertElements as $alertEl) {
            $sender = self::getText($alertEl, 'sender');
            
            $infoElements = [];
            $infoChildren = $alertEl->children(self::CAP_NS);
            if (isset($infoChildren->info)) {
                foreach ($infoChildren->info as $info) {
                    $infoElements[] = $info;
                }
            }

            if (empty($infoElements) && isset($alertEl->info)) {
                foreach ($alertEl->info as $info) {
                    $infoElements[] = $info;
                }
            }
            
            if (empty($infoElements)) {
                continue;
            }

            $info = self::pickSpanish($infoElements) ?? $infoElements[0];

            $severityRaw = self::getText($info, 'severity') ?? 'Unknown';
            $severity = self::SEVERITY_MAP[$severityRaw] ?? 'yellow';
            
            $headline = self::getText($info, 'headline') ?? '';
            $description = self::getText($info, 'description') ?? $headline;
            $eventType = self::getText($info, 'event');
            $onset = self::getText($info, 'onset');
            $expires = self::getText($info, 'expires');
            $certainty = self::getText($info, 'certainty');
            $urgency = self::getText($info, 'urgency');
            $web = self::getText($info, 'web');

            // Recopilar descripciones de área
            $areaParts = [];
            $areaChildren = $info->children(self::CAP_NS);
            if (isset($areaChildren->area)) {
                foreach ($areaChildren->area as $area) {
                    $areaDesc = self::getText($area, 'areaDesc');
                    if ($areaDesc) {
                        $areaParts[] = $areaDesc;
                    }
                }
            }

            if (empty($areaParts) && isset($info->area)) {
                foreach ($info->area as $area) {
                    $areaDesc = self::getText($area, 'areaDesc');
                    if ($areaDesc) {
                        $areaParts[] = $areaDesc;
                    }
                }
            }

            $areaStr = !empty($areaParts) ? implode("; ", $areaParts) : null;

            $alerts[] = new Alert(
                source: 'aemet',
                severity: $severity,
                headline: $headline,
                description: $description,
                area: $areaStr,
                event_type: $eventType,
                onset: $onset,
                expires: $expires,
                certainty: $certainty,
                urgency: $urgency,
                sender: $sender,
                web: $web,
            );
        }

        return $alerts;
    }

    /**
     * Deduplicar alertas: agrupar por headline+severity+vigencia, unir áreas
     *
     * AEMET genera un CAP XML por zona. Misma alerta (ej: "tormentas verde")
     * aparece repetida docenas de veces con distinta zona. Este método las
     * agrupa y une las áreas en una sola alerta.
     */
    private static function deduplicateAlerts(array $alerts): array {
        // Filtrar alertas de nivel verde (sin riesgo significativo)
        $alerts = array_filter($alerts, fn(Alert $a) => $a->severity !== 'green');

        $grouped = [];
        foreach ($alerts as $alert) {
            // Limpiar headline: quitar ". CCAA" genérico (Comunidades Autónomas)
            $alert->headline = preg_replace('/\.\s*CCAA\s*$/u', '', $alert->headline);

            // Quitar sufijo de zona del headline (ej: ". Costa - Noroeste de A Coruña")
            // AEMET incluye la zona en el headline, pero ya se muestra aparte en el campo area
            if ($alert->area) {
                $areas = array_map('trim', explode('; ', $alert->area));
                foreach ($areas as $areaDesc) {
                    $suffix = '. ' . $areaDesc;
                    if (str_ends_with($alert->headline, $suffix)) {
                        $alert->headline = substr($alert->headline, 0, -strlen($suffix));
                        break;
                    }
                }
            }

            $key = $alert->severity . '|' . $alert->headline . '|' . ($alert->onset ?? '') . '|' . ($alert->expires ?? '');
            if (!isset($grouped[$key])) {
                $grouped[$key] = $alert;
            } else {
                // Unir áreas
                $existing = $grouped[$key];
                if ($alert->area && $existing->area) {
                    $existingAreas = array_map('trim', explode('; ', $existing->area));
                    $newAreas = array_map('trim', explode('; ', $alert->area));
                    $merged = array_unique(array_merge($existingAreas, $newAreas));
                    $existing->area = implode('; ', $merged);
                } elseif ($alert->area) {
                    $existing->area = $alert->area;
                }
            }
        }
        return array_values($grouped);
    }

    /**
     * Obtener la URL de datos para previsión horaria de un municipio
     */
    private static function getHourlyDataUrl(string $municipioId): string {
        $url = self::BASE_URL . str_replace("{municipio}", $municipioId, self::HOURLY_ENDPOINT);
        $body = json_decode(self::request($url), true);

        if (!isset($body['datos'])) {
            throw new Exception("AEMET hourly response missing 'datos' field");
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

        try {
            $datosUrl = self::getHourlyDataUrl($municipioId);
            $rawData = self::request($datosUrl);

            $json = json_decode($rawData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON: " . json_last_error_msg() . " - raw: " . substr($rawData, 0, 200));
            }
            if (!is_array($json) || empty($json)) {
                throw new Exception("Empty or non-array JSON response");
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

                // Viento (campo separado) y rachas máximas (campo separado)
                $wind = self::indexWind($day['viento'] ?? []);
                $gusts = self::indexGust($day['rachaMax'] ?? []);

                // Generar un HourlyForecast por cada hora que tenga temperatura
                foreach ($temps as $periodo => $tempVal) {
                    $hour = str_pad($periodo, 2, '0', STR_PAD_LEFT);
                    $datetime = "{$dateBase}T{$hour}:00:00";

                    $skyEntry = $sky[$periodo] ?? null;

                    $hours[] = new HourlyForecast(
                        datetime: $datetime,
                        temperature: $tempVal !== null ? (int)$tempVal : null,
                        feelsLike: isset($feels[$periodo]) ? (int)$feels[$periodo] : null,
                        humidity: isset($humidity[$periodo]) ? (int)$humidity[$periodo] : null,
                        precipProb: $precipProb[$periodo] ?? null,
                        precipAmount: $precip[$periodo] ?? null,
                        windDir: $wind[$periodo]['dir'] ?? null,
                        windSpeed: $wind[$periodo]['speed'] ?? null,
                        windGust: $gusts[$periodo] ?? null,
                        skyDescription: is_array($skyEntry) ? ($skyEntry['descripcion'] ?? null) : null,
                        skyCode: is_array($skyEntry) ? ($skyEntry['value'] ?? null) : (($skyEntry !== null) ? (string)$skyEntry : null),
                    );
                }
            }

            return [
                'name' => $name,
                'province' => $province,
                'issued' => $issued,
                'hours' => $hours,
            ];
        } catch (Exception $exc) {
            echo "[aemet] Error fetching hourly forecast: {$exc->getMessage()}\n";
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
     * Indexar viento desde campo "viento" (direccion, velocidad, periodo)
     */
    private static function indexWind(array $entries): array {
        $indexed = [];
        foreach ($entries as $entry) {
            $periodo = $entry['periodo'] ?? '';
            if ($periodo === '') continue;
            $dir = $entry['direccion'] ?? '';
            $speed = $entry['velocidad'] ?? '';
            if ($dir !== '' || $speed !== '') {
                $indexed[(int)$periodo] = [
                    'dir' => $dir !== '' ? $dir : null,
                    'speed' => $speed !== '' ? (int)$speed : null,
                ];
            }
        }
        return $indexed;
    }

    /**
     * Indexar rachas máximas desde campo "rachaMax" (value, periodo)
     * El periodo puede ser individual ("00") o rango ("00-06")
     */
    private static function indexGust(array $entries): array {
        $indexed = [];
        foreach ($entries as $entry) {
            $periodo = $entry['periodo'] ?? '';
            $val = $entry['value'] ?? '';
            if ($val === '' || $periodo === '') continue;
            $gust = (int)$val;

            if (strpos($periodo, '-') !== false) {
                // Rango (ej: "00-06", "06-12")
                $parts = explode('-', $periodo);
                $start = (int)$parts[0];
                $end = (int)$parts[1];
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
     * Fetch actualizado: obtener alertas de AEMET
     *
     * El endpoint datos devuelve un archivo tar.gz con múltiples
     * ficheros CAP XML individuales (uno por aviso/zona).
     */
    public static function fetch(): array {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            echo "[aemet] AEMET_API_KEY not set, skipping.\n";
            return [];
        }

        try {
            $datosUrl = self::getDataUrl('esp');
            $rawData = self::request($datosUrl, '*/*');

            // Validar que tenemos datos
            if (empty($rawData)) {
                echo "[aemet] Empty response from API.\n";
                return [];
            }

            // Detectar formato de respuesta
            $trimmed = ltrim($rawData);

            // 1. Verificar si es JSON (posible error del API)
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $jsonData = json_decode($rawData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    // Es una respuesta JSON, probablemente un error
                    $errorMsg = $jsonData['descripcion'] ?? $jsonData['mensaje'] ?? 'Unknown JSON response';
                    echo "[aemet] API returned JSON response: {$errorMsg}\n";
                    return [];
                }
            }

            // 2. Verificar si es XML directo
            if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<')) {
                return self::parseCAP($rawData);
            }

            // 3. Detectar formato de archivo tar (comprimido o sin comprimir)
            // AEMET puede devolver tar.gz (magic bytes 1f 8b) o tar sin comprimir
            // (empieza con el nombre del fichero, ej: "Z_CAP_C_LEMM_...")
            $isGzip = (strlen($rawData) >= 2 && substr($rawData, 0, 2) === "\x1f\x8b");
            $xmlFiles = self::extractTar($rawData, $isGzip);

            if (empty($xmlFiles)) {
                echo "[aemet] No XML files found in archive.\n";
                return [];
            }

            $allAlerts = [];
            foreach ($xmlFiles as $xmlContent) {
                try {
                    $alerts = self::parseCAP($xmlContent);
                    $allAlerts = array_merge($allAlerts, $alerts);
                } catch (Exception $e) {
                    // Saltar archivos XML inválidos
                    continue;
                }
            }

            return self::deduplicateAlerts($allAlerts);
        } catch (Exception $exc) {
            echo "[aemet] Error fetching alerts: {$exc->getMessage()}\n";
            return [];
        }
    }
}
