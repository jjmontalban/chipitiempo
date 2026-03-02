<?php

/**
 * ChipiTiempo - Constructor de HTML
 * 
 * Genera HTML para alertas y previsión meteorológica
 */

require_once __DIR__ . '/Config/AppConfig.php';

class HtmlBuilder {
    private const SEVERITY_EMOJI = ["red" => "🔴", "orange" => "🟠", "yellow" => "🟡", "green" => "✅"];
    
    private const CADIZ_MUNICIPALITIES = [
        'Algeciras', 'Arcos de la Frontera', 'Barbate', 'Cádiz (capital)', 'Chiclana de la Frontera',
        'Chipiona', 'Conil de la Frontera', 'El Puerto de Santa María', 'Espera', 'Grazalema',
        'Jerez de la Frontera', 'Jimena de la Frontera', 'La Línea de la Concepción', 'Los Barrios',
        'Medina-Sidonia', 'Olvera', 'Prado del Rey', 'Puerto Real', 'Rota', 'San Fernando',
        'San Roque', 'Sanlúcar de Barrameda', 'Tarifa', 'Ubrique', 'Vejer de la Frontera',
    ];

    /**
     * Construir página HTML completa
     */
    public static function buildPage(array $alerts, array $forecasts = []): string {
        $timestamp = time();  // Timestamp Unix actual
        $weatherHtml = self::buildWeatherSection($forecasts);
        $municFilterHtml = self::buildMunicipalityFilter($forecasts);
        $alertsHtml = self::buildAlertsSection($alerts);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="ChipiTiempo - El tiempo en Chipiona y comarca">
<title>ChipiTiempo - El tiempo en Chipiona</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; max-width: 900px; }
h1, h2, h3 { color: #333; }
hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
ul { padding-left: 20px; }
a { color: #0066cc; }
small { color: #666; }
.header-line { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.header-line h1 { margin: 0; }
.header-line em { margin: 0; font-size: 14px; }
.munic-filter { margin: 10px 0; padding: 10px; background: #f0f8ff; border-radius: 4px; line-height: 2; border-left: 4px solid #0066cc; }
.munic-filter a { white-space: nowrap; }
.forecast-scroll { overflow-x: auto; }
.forecast { width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 14px; }
.forecast th { background: #2c3e50; color: #fff; padding: 6px 8px; text-align: left; white-space: nowrap; }
.forecast td { padding: 5px 8px; border-bottom: 1px solid #eee; white-space: nowrap; }
.forecast tr:hover { background: #f5f8fc; }
.rain-med { color: #e67e22; font-weight: bold; }
.rain-high { color: #e74c3c; font-weight: bold; }
</style>
</head>
<body>

<div class="header-line">
  <h1>ChipiTiempo</h1>
  <em id="updateTime">Actualizado hace 0 minutos</em>
  <input type="hidden" id="lastUpdateTime" value="{$timestamp}">
</div>

<hr>

{$municFilterHtml}

{$weatherHtml}

<hr>

<h2>ALERTAS Y AVISOS ACTIVOS</h2>

{$alertsHtml}


<p><strong>Fuentes oficiales:</strong></p>
<ul>
<li><strong>AEMET</strong> - Alertas meteorológicas: <a href="https://www.aemet.es/es/eltiempo/prediccion/avisos">aemet.es/avisos</a></li>
</ul>

<hr>

<p><small>ChipiTiempo - Datos de <a href="https://www.aemet.es">AEMET</a>. En caso de emergencia, llama al <strong>112</strong>.</small></p>

<script>
var currentMunicipality = 'Chipiona';

function updateDisplay() {
    var forecasts = document.querySelectorAll('.forecast-section');
    for (var i = 0; i < forecasts.length; i++) {
        var munic = forecasts[i].getAttribute('data-munic') || '';
        forecasts[i].style.display = (munic === currentMunicipality) ? '' : 'none';
    }
    
    var alerts = document.querySelectorAll('.al');
    for (var i = 0; i < alerts.length; i++) {
        var munic = alerts[i].getAttribute('data-munic') || '';
        alerts[i].style.display = (munic === currentMunicipality || munic === '') ? '' : 'none';
    }
    
    var groups = document.querySelectorAll('.src-group');
    for (var i = 0; i < groups.length; i++) {
        var visible = false;
        var items = groups[i].querySelectorAll('.al');
        for (var j = 0; j < items.length; j++) {
            if (items[j].style.display !== 'none') {
                visible = true;
                break;
            }
        }
        groups[i].style.display = visible ? '' : 'none';
    }
}

function fm(munic) {
    currentMunicipality = munic;
    updateDisplay();
    var links = document.querySelectorAll('.munic-filter a');
    for (var i = 0; i < links.length; i++) {
        links[i].style.fontWeight = (links[i].getAttribute('data-munic') === munic) ? 'bold' : 'normal';
    }
}

function updateUpdateTime() {
    var lastUpdateEl = document.getElementById('lastUpdateTime');
    if (!lastUpdateEl) return;
    
    var lastUpdateTime = parseInt(lastUpdateEl.value) * 1000;  // Convertir a milisegundos
    var now = Date.now();
    var diffMs = now - lastUpdateTime;
    var diffMinutes = Math.floor(diffMs / 60000);
    
    var updateTimeEl = document.getElementById('updateTime');
    if (updateTimeEl) {
        if (diffMinutes === 0) {
            updateTimeEl.textContent = 'Actualizado hace 0 minutos';
        } else if (diffMinutes === 1) {
            updateTimeEl.textContent = 'Actualizado hace 1 minuto';
        } else {
            updateTimeEl.textContent = 'Actualizado hace ' + diffMinutes + ' minutos';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateDisplay();
    fm('Chipiona');
    updateUpdateTime();
    setInterval(updateUpdateTime, 60000);  // Actualizar cada minuto
});
</script>

</body>
</html>
HTML;
    }
    
    /**
     * Construir sección de previsión meteorológica
     */
    public static function buildWeatherSection(array $forecasts): string {
        if (empty($forecasts)) {
            return "<p><strong>No hay datos de previsión disponibles en este momento.</strong></p>\n" .
                   "<p><small>Es posible que el servicio de AEMET esté temporalmente inaccesible. Los datos se actualizarán automáticamente cuando el servicio esté disponible.</small></p>\n";
        }
        
        if (isset($forecasts['name'])) {
            return self::renderForecast($forecasts, true) . self::renderDailyForecast($forecasts, true);
        }
        
        $html = "\n";
        foreach ($forecasts as $municipality => $forecast) {
            $html .= "<div class=\"forecast-section\" data-munic=\"{$municipality}\">\n";
            $html .= self::renderForecast($forecast, false);
            $html .= self::renderDailyForecast($forecast, false);
            $html .= "</div>\n";
        }
        return $html;
    }
    
    
    private static function renderDailyForecast(array $forecast, bool $title = true): string {
        $dailyHours = $forecast['daily_hours'] ?? [];
        
        if (empty($dailyHours)) return '';

        $html = "";
        if ($title) {
            $html .= "\n<h2>PREVISI&Oacute;N DIARIA</h2>\n";
        }
        
        $html .= "<div class=\"forecast-scroll\"><table class=\"forecast\">\n";
        $html .= "<tr><th>Fecha</th><th>M&iacute;n/M&aacute;x</th><th>Cielo</th><th>Lluvia</th><th>Viento</th></tr>\n";

        foreach ($dailyHours as $day) {
            $dateLabel = self::formatDateLabel($day->date);
            $tempRange = '';
            if ($day->tempMin !== null || $day->tempMax !== null) {
                $minStr = $day->tempMin !== null ? $day->tempMin . '&deg;' : '-';
                $maxStr = $day->tempMax !== null ? $day->tempMax . '&deg;' : '-';
                $tempRange = "{$minStr} / {$maxStr}";
            }
            
            $sky = $day->skyDescription ? htmlspecialchars($day->skyDescription, ENT_QUOTES, 'UTF-8') : '-';
            
            $rain = '-';
            if ($day->precipProb !== null && $day->precipProb > 0) {
                $rain = "{$day->precipProb}%";
            }
            
            $wind = '-';
            if ($day->windDir !== null && $day->windSpeed !== null) {
                $arrow = $day->windArrow();
                $wind = "{$arrow} {$day->windDir} {$day->windSpeed} km/h";
            }
            
            $rainClass = $day->precipProb >= 60 ? ' class="rain-high"' : ($day->precipProb >= 30 ? ' class="rain-med"' : '');
            
            $html .= "<tr><td><strong>{$dateLabel}</strong></td><td>{$tempRange}</td><td>{$sky}</td>";
            $html .= "<td{$rainClass}>{$rain}</td><td>{$wind}</td></tr>\n";
        }
        
        $html .= "</table></div>\n";
        
        return $html;
    }
    
    /**
     * Mapeo de códigos AEMET a emojis meteorológicos
     * AEMET usa códigos numéricos para el estado del cielo (estadoCielo)
     */
    private static function getWeatherEmoji(?string $skyCode): string {
        if (!$skyCode) return '';
        
        // Remover la 'n' de códigos nocturnos para el mapeo base
        $code = str_replace('n', '', $skyCode);
        
        $emojiMap = [
            // Cielo despejado/poco nuboso (formato de un dígito y dos dígitos)
            '1'  => '☀️',   // Despejado
            '11' => '☀️',   // Despejado
            '2'  => '🌤️',  // Poco nuboso
            '12' => '🌤️',  // Poco nuboso
            '3'  => '⛅',   // Intervalos nubosos
            '13' => '⛅',   // Intervalos nubosos
            '14' => '🌥️',  // Nuboso
            '15' => '☁️',   // Muy nuboso
            '16' => '☁️',   // Cubierto
            '17' => '🌤️',  // Nubes altas

            // Lluvia escasa (drizzle)
            '23' => '🌦️',  // Intervalos nubosos con lluvia escasa
            '24' => '🌧️',  // Nuboso con lluvia escasa
            '25' => '🌧️',  // Muy nuboso con lluvia escasa
            '26' => '🌧️',  // Cubierto con lluvia escasa

            // Nieve escasa
            '33' => '🌨️',  // Intervalos nubosos con nieve escasa
            '34' => '🌨️',  // Nuboso con nieve escasa
            '35' => '🌨️',  // Muy nuboso con nieve escasa
            '36' => '❄️',   // Cubierto con nieve escasa

            // Lluvia
            '43' => '🌧️',  // Intervalos nubosos con lluvia
            '44' => '🌧️',  // Nuboso con lluvia
            '45' => '🌧️',  // Muy nuboso con lluvia
            '46' => '🌧️',  // Cubierto con lluvia

            // Nieve
            '51' => '🌨️',  // Intervalos nubosos con nieve
            '52' => '🌨️',  // Nuboso con nieve
            '53' => '🌨️',  // Muy nuboso con nieve
            '54' => '❄️',   // Cubierto con nieve

            // Tormenta
            '61' => '⛈️',  // Intervalos nubosos con tormenta
            '62' => '⛈️',  // Nuboso con tormenta
            '63' => '⛈️',  // Muy nuboso con tormenta
            '64' => '⛈️',  // Cubierto con tormenta

            // Tormenta con nieve
            '71' => '⛈️',  // Intervalos nubosos con nieve y tormenta
            '72' => '⛈️',  // Nuboso con nieve y tormenta
            '73' => '⛈️',  // Muy nuboso con nieve y tormenta
            '74' => '⛈️',  // Cubierto con nieve y tormenta

            // Códigos de uso interno/legacy
            '80' => '🌦️',  // Lluvia débil
            '81' => '🌧️',  // Lluvia moderada
            '82' => '⛈️',  // Lluvia fuerte/tormentas
        ];
        
        return $emojiMap[$code] ?? '';
    }

    /**
     * Determinar si un código de cielo AEMET indica precipitación activa
     * (lluvia escasa: 23-26, nieve escasa: 33-36, lluvia: 43-46,
     *  nieve: 51-54, tormenta: 61-64, tormenta+nieve: 71-74, legacy: 80-82)
     */
    private static function isRainCode(?string $skyCode): bool {
        if (!$skyCode) return false;
        $code = str_replace('n', '', $skyCode);
        $precipCodes = ['23', '24', '25', '26', '33', '34', '35', '36',
                        '43', '44', '45', '46', '51', '52', '53', '54',
                        '61', '62', '63', '64', '71', '72', '73', '74',
                        '80', '81', '82'];
        return in_array($code, $precipCodes);
    }
    
    private static function renderForecast(array $forecast, bool $title = true): string {
        $name = htmlspecialchars($forecast['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $province = htmlspecialchars($forecast['province'] ?? '', ENT_QUOTES, 'UTF-8');
        $hours = $forecast['hours'] ?? [];

        if (empty($hours)) return '';

        $location = ($title && $province) ? "$name ($province)" : $name;
        $header = $title ? "<h2>PREVISI&Oacute;N HORARIA &mdash; {$location}</h2>\n" : "<h3>{$name}</h3>\n";
        
        // Filtrar solo pronóstico futuro: si estamos a las 9:30, mostrar desde las 10:00
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $minutes = (int)$now->format('i');
        
        if ($minutes > 0) {
            // Si hay minutos (ej: 9:30), mostrar desde la próxima hora (10:00)
            $now->add(new \DateInterval('PT1H'));
        }
        
        $nextHourIso = $now->format('Y-m-d\TH:00:00');
        $filtered = array_filter($hours, fn($h) => $h->datetime >= $nextHourIso) ?: $hours;

        $byDay = [];
        foreach ($filtered as $h) {
            $date = substr($h->datetime, 0, 10);
            $byDay[$date][] = $h;
        }

        $html = $header;
        foreach ($byDay as $date => $dayHours) {
            $temps = array_filter(array_map(fn($h) => $h->temperature, $dayHours), fn($t) => $t !== null);
            $tempRange = $temps ? " &mdash; M&iacute;n: " . min($temps) . "&deg; / M&aacute;x: " . max($temps) . "&deg;" : "";
            $dayLabel = self::formatDateLabel($date);
            
            $html .= "<h3>{$dayLabel}{$tempRange}</h3>\n<div class=\"forecast-scroll\"><table class=\"forecast\">\n";
            $html .= "<tr><th>Hora</th><th>&deg;C</th><th>Sens.</th><th></th><th>Viento</th><th>Lluvia</th><th>Prob.</th><th>Cielo</th></tr>\n";

            foreach ($dayHours as $h) {
                $hour = substr($h->datetime, 11, 5);
                $temp = $h->temperature !== null ? "{$h->temperature}&deg;" : '-';
                $feels = $h->feelsLike !== null ? "{$h->feelsLike}&deg;" : '-';
                $rainAmount = ($h->precipProb > 0 && $h->precipAmount && $h->precipAmount !== '0') ? "{$h->precipAmount} mm" : '-';
                $rainProb = $h->precipProb !== null ? "{$h->precipProb}%" : '-';
                $probClass = $h->precipProb >= 60 ? ' class="rain-high"' : ($h->precipProb >= 30 ? ' class="rain-med"' : '');
                $sky = $h->skyDescription ? htmlspecialchars($h->skyDescription, ENT_QUOTES, 'UTF-8') : '-';
                
                // Generar emoji icono basado en el código AEMET.
                // Si la probabilidad de lluvia es significativa (≥40%) pero el código
                // del cielo no indica precipitación activa, mostrar icono de lluvia
                // posible para mantener coherencia visual con la columna de probabilidad.
                $iconEmoji = self::getWeatherEmoji($h->skyCode);
                if (!self::isRainCode($h->skyCode) && $h->precipProb !== null && $h->precipProb >= 40) {
                    $iconEmoji = '🌦️';
                }
                $iconHtml = $iconEmoji ? "<span style=\"font-size:28px;\">{$iconEmoji}</span>" : '';
                
                $windStr = '-';
                if ($h->windDir !== null && $h->windSpeed !== null) {
                    $arrow = $h->windArrow();
                    $windStr = "{$arrow} {$h->windDir} {$h->windSpeed} km/h";
                    if ($h->windGust !== null && $h->windGust > $h->windSpeed) {
                        $windStr .= " <small>(racha {$h->windGust})</small>";
                    }
                }

                $html .= "<tr><td><strong>{$hour}</strong></td><td>{$temp}</td><td>{$feels}</td><td>{$iconHtml}</td><td>{$windStr}</td>";
                $html .= "<td>{$rainAmount}</td><td{$probClass}>{$rainProb}</td><td>{$sky}</td></tr>\n";
            }
            $html .= "</table></div>\n";
        }
        return $html;
    }
    
    private static function formatDateLabel(string $date): string {
        $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
                   7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $parts = explode('-', $date);
        $day = (int)$parts[2];
        $month = $months[(int)$parts[1]] ?? '';
        $prefix = ($date === $today) ? 'Hoy, ' : (($date === $tomorrow) ? 'Ma&ntilde;ana, ' : '');
        return "{$prefix}{$day} de {$month}";
    }
    
    public static function buildMunicipalityFilter(array $forecasts = []): string {
        $html = "<div class=\"munic-filter\">";
        
        // Solo mostrar municipios que tienen datos de previsión
        $municipalities = array_keys($forecasts);
        sort($municipalities);
        
        $first = true;
        foreach ($municipalities as $munic) {
            $safe = htmlspecialchars($munic, ENT_QUOTES, 'UTF-8');
            if (!$first) {
                $html .= " | ";
            }
            $html .= "<a href=\"#\" onclick=\"fm('{$safe}');return false\" data-munic=\"{$safe}\">{$safe}</a>";
            $first = false;
        }
        $html .= "\n</div>\n";
        return $html;
    }
    
    public static function buildAlertsSection(array $alerts): string {
        if (empty($alerts)) {
            return "<p><strong>No hay alertas activas en este momento.</strong></p>\n";
        }

        // Filtrar solo alertas de Andalucía
        $alerts = array_filter($alerts, fn($a) => self::isAndalusianAlert($a));
        
        if (empty($alerts)) {
            return "<p><strong>No hay alertas en Andalucía en este momento.</strong></p>\n";
        }

        $grouped = [];
        foreach ($alerts as $alert) {
            $grouped[$alert->source][] = $alert;
        }

        $html = "";
        foreach ($grouped as $source => $sourceAlerts) {
            $html .= "<div class=\"src-group\">\n<h3>" . htmlspecialchars(ucfirst($source), ENT_QUOTES, 'UTF-8') . "</h3>\n<ul>\n";
            foreach ($sourceAlerts as $alert) {
                $emoji = self::SEVERITY_EMOJI[$alert->severity] ?? "";
                $headline = htmlspecialchars($alert->headline ?: $alert->description, ENT_QUOTES, 'UTF-8');
                $areaHtml = $alert->area ? "<br><small>Zona: " . htmlspecialchars($alert->area, ENT_QUOTES, 'UTF-8') . "</small>" : '';
                
                $municipalities = self::extractMunicipalities($alert->area ?? '');
                $munic = $municipalities ? htmlspecialchars($municipalities[0], ENT_QUOTES, 'UTF-8') : '';
                
                $html .= "<li class=\"al\" data-munic=\"{$munic}\">{$emoji} <strong>{$headline}</strong>{$areaHtml}</li>\n";
            }
            $html .= "</ul>\n</div>\n";
        }
        return $html;
    }
    
    private static function isAndalusianAlert($alert): bool {
        if (!$alert->area) {
            return false;
        }
        
        $area = mb_strtolower($alert->area);
        
        // Provincias de Andalucía
        $andalusianRegions = [
            'almería',
            'cádiz',
            'córdoba',
            'granada',
            'huelva',
            'jaén',
            'málaga',
            'sevilla',
            'andalucía',
        ];
        
        foreach ($andalusianRegions as $region) {
            if (mb_strpos($area, $region) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function extractMunicipalities(string $area): array {
        if (!$area) return [];
        $matched = [];
        foreach (self::CADIZ_MUNICIPALITIES as $municipality) {
            $municName = str_replace(' (capital)', '', $municipality);
            if (mb_stripos($area, $municName) !== false) {
                $matched[] = $municipality;
            }
        }
        return array_unique($matched);
    }
}
