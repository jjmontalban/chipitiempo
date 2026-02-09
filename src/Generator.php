<?php

/**
 * ChipiTiempo - Generador de HTML: previsión horaria y alertas
 */

class AlertGenerator {
    private const SEVERITY_ORDER = [
        "red" => 0,
        "orange" => 1,
        "yellow" => 2,
        "green" => 3,
    ];

    private const SEVERITY_LABEL = [
        "red" => "Rojo",
        "orange" => "Naranja",
        "yellow" => "Amarillo",
        "green" => "Verde",
    ];

    private const SEVERITY_EMOJI = [
        "red" => "🔴",
        "orange" => "🟠",
        "yellow" => "🟡",
        "green" => "✅",
    ];

    private const SOURCE_LABELS = [
        "aemet" => "Meteorología (AEMET)",
    ];

    /** Configuración local: provincias permitidas en filtro */
    private const ALLOWED_PROVINCES = [
        'Cádiz',
        'Huelva',
        'Sevilla',
        'Málaga',
    ];

    /** Configuración local: municipios de Cádiz */
    private const CADIZ_MUNICIPALITIES = [
        'Algeciras',
        'Arcos de la Frontera',
        'Barbate',
        'Cádiz (capital)',
        'Chiclana de la Frontera',
        'Chipiona',
        'Conil de la Frontera',
        'El Puerto de Santa María',
        'Espera',
        'Grazalema',
        'Jerez de la Frontera',
        'Jimena de la Frontera',
        'La Línea de la Concepción',
        'Los Barrios',
        'Medina-Sidonia',
        'Olvera',
        'Prado del Rey',
        'Puerto Real',
        'Rota',
        'San Fernando',
        'San Roque',
        'Sanlúcar de Barrameda',
        'Tarifa',
        'Ubrique',
        'Vejer de la Frontera',
    ];

    /** Códigos INE de municipios de Cádiz para la API de AEMET */
    private const MUNICIPALITY_CODES = [
        'Algeciras' => '11004',
        'Arcos de la Frontera' => '11006',
        'Barbate' => '11007',
        'Cádiz (capital)' => '11012',
        'Chiclana de la Frontera' => '11015',
        'Chipiona' => '11016',
        'Conil de la Frontera' => '11014',
        'El Puerto de Santa María' => '11027',
        'Espera' => '11017',
        'Grazalema' => '11019',
        'Jerez de la Frontera' => '11020',
        'Jimena de la Frontera' => '11021',
        'La Línea de la Concepción' => '11022',
        'Los Barrios' => '11008',
        'Medina-Sidonia' => '11023',
        'Olvera' => '11024',
        'Prado del Rey' => '11026',
        'Puerto Real' => '11028',
        'Rota' => '11030',
        'San Fernando' => '11031',
        'San Roque' => '11033',
        'Sanlúcar de Barrameda' => '11034',
        'Tarifa' => '11035',
        'Ubrique' => '11038',
        'Vejer de la Frontera' => '11039',
    ];

    private const DEFAULT_MUNICIPALITY = 'Chipiona';

    /** Palabras clave para mapear zonas de alerta a provincias */
    private const PROVINCE_KEYWORDS = [
        'A Coruña' => ['Coruña'],
        'Álava' => ['Álava', 'Araba'],
        'Albacete' => ['Albacete'],
        'Alicante' => ['Alicante', 'Alacant'],
        'Almería' => ['Almería'],
        'Asturias' => ['Asturias'],
        'Ávila' => ['Ávila'],
        'Badajoz' => ['Badajoz'],
        'Barcelona' => ['Barcelona'],
        'Bizkaia' => ['Bizkaia', 'Vizcaya'],
        'Burgos' => ['Burgos'],
        'Cáceres' => ['Cáceres'],
        'Cádiz' => ['Cádiz'],
        'Cantabria' => ['Cantabria'],
        'Castellón' => ['Castellón', 'Castelló'],
        'Ceuta' => ['Ceuta'],
        'Ciudad Real' => ['Ciudad Real'],
        'Córdoba' => ['Córdoba'],
        'Cuenca' => ['Cuenca'],
        'Gipuzkoa' => ['Gipuzkoa', 'Guipúzcoa'],
        'Girona' => ['Girona', 'Gerona'],
        'Granada' => ['Granada'],
        'Guadalajara' => ['Guadalajara'],
        'Huelva' => ['Huelva'],
        'Huesca' => ['Huesca', 'oscense'],
        'Illes Balears' => ['Balears', 'Baleares', 'Mallorca', 'Menorca', 'Ibiza', 'Eivissa', 'Formentera'],
        'Jaén' => ['Jaén'],
        'León' => ['León'],
        'Lleida' => ['Lleida', 'Lérida'],
        'Lugo' => ['Lugo'],
        'Madrid' => ['Madrid'],
        'Málaga' => ['Málaga'],
        'Melilla' => ['Melilla'],
        'Murcia' => ['Murcia'],
        'Navarra' => ['Navarra', 'Nafarroa'],
        'Ourense' => ['Ourense', 'Orense'],
        'Palencia' => ['Palencia'],
        'Las Palmas' => ['Las Palmas', 'Gran Canaria', 'Lanzarote', 'Fuerteventura'],
        'Pontevedra' => ['Pontevedra'],
        'La Rioja' => ['Rioja'],
        'Salamanca' => ['Salamanca'],
        'S.C. Tenerife' => ['Tenerife', 'La Palma', 'La Gomera', 'El Hierro'],
        'Segovia' => ['Segovia'],
        'Sevilla' => ['Sevilla'],
        'Soria' => ['Soria'],
        'Tarragona' => ['Tarragona'],
        'Teruel' => ['Teruel'],
        'Toledo' => ['Toledo'],
        'Valencia' => ['Valencia', 'València'],
        'Valladolid' => ['Valladolid'],
        'Zamora' => ['Zamora'],
        'Zaragoza' => ['Zaragoza'],
    ];

    /**
     * Recopilar alertas de AEMET
     */
    public static function collectAlerts(): array {
        require_once __DIR__ . "/Sources/AEMET.php";
        
        try {
            $alerts = AEMETSource::fetch();
            echo "[AEMET] " . count($alerts) . " alertas obtenidas\n";
            
            // Ordenar: más severas primero
            usort($alerts, function(Alert $a, Alert $b) {
                $sevA = self::SEVERITY_ORDER[$a->severity] ?? 99;
                $sevB = self::SEVERITY_ORDER[$b->severity] ?? 99;
                return $sevA <=> $sevB;
            });
            
            return $alerts;
        } catch (Exception $exc) {
            echo "[AEMET] Error: {$exc->getMessage()}\n";
            return [];
        }
    }

    /**
     * Agrupar alertas por fuente
     */
    public static function groupBySource(array $alerts): array {
        $grouped = [];
        foreach ($alerts as $alert) {
            $grouped[$alert->source][] = $alert;
        }
        return $grouped;
    }

    /**
     * Extraer provincias de un texto de área mediante palabras clave
     * Filtra solo las provincias permitidas en ALLOWED_PROVINCES
     */
    public static function extractProvinces(string $area): array {
        $matched = [];
        foreach (self::PROVINCE_KEYWORDS as $province => $keywords) {
            // Solo incluir provincias permitidas
            if (!in_array($province, self::ALLOWED_PROVINCES)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if (mb_stripos($area, $keyword) !== false) {
                    $matched[] = $province;
                    break;
                }
            }
        }
        return array_unique($matched);
    }

    /**
     * Extraer municipios de Cádiz de un texto de área
     */
    public static function extractMunicipalities(string $area): array {
        $matched = [];
        foreach (self::CADIZ_MUNICIPALITIES as $municipality) {
            // Buscar el municipio (sin el "(capital)" si existe)
            $municName = str_replace(' (capital)', '', $municipality);
            if (mb_stripos($area, $municName) !== false) {
                $matched[] = $municipality;
            }
        }
        return array_unique($matched);
    }

    /**
     * Obtener código INE de un municipio
     */
    public static function getMunicipalityCode(string $name): ?string {
        return self::MUNICIPALITY_CODES[$name] ?? null;
    }

    /**
     * Recopilar previsión horaria para un municipio
     */
    public static function collectForecast(string $municipality = null): array {
        $municipality = $municipality ?? self::DEFAULT_MUNICIPALITY;
        $code = self::getMunicipalityCode($municipality);

        if (!$code) {
            echo "[forecast] Unknown municipality: {$municipality}\n";
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => []];
        }

        require_once __DIR__ . "/Sources/AEMET.php";
        try {
            $forecast = AEMETSource::fetchHourlyForecast($code);
            echo "[forecast] " . count($forecast['hours']) . " horas de previsión para {$municipality}\n";
            return $forecast;
        } catch (Exception $exc) {
            echo "[forecast] Error: {$exc->getMessage()}\n";
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => []];
        }
    }

    /**
     * Renderizar sección de previsión horaria
     */
    public static function renderWeatherSection(array $forecast): string {
        $name = htmlspecialchars($forecast['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $province = htmlspecialchars($forecast['province'] ?? '', ENT_QUOTES, 'UTF-8');
        $hours = $forecast['hours'] ?? [];

        if (empty($hours)) {
            return "<h2>PREVISI&Oacute;N HORARIA</h2>\n<p>No hay datos de previsi&oacute;n disponibles.</p>\n";
        }

        $location = $name;
        if ($province) {
            $location .= " ({$province})";
        }

        // Filtrar: solo horas desde la hora actual en adelante
        $nowIso = date('Y-m-d\TH:') . '00:00';
        $filtered = array_filter($hours, fn($h) => $h->datetime >= $nowIso);
        if (empty($filtered)) {
            $filtered = $hours; // fallback: mostrar todo si no hay horas futuras
        }

        // Agrupar horas por día
        $byDay = [];
        foreach ($filtered as $h) {
            $date = substr($h->datetime, 0, 10);
            $byDay[$date][] = $h;
        }

        $html = "<h2>PREVISI&Oacute;N HORARIA &mdash; {$location}</h2>\n";

        foreach ($byDay as $date => $dayHours) {
            $dayLabel = self::formatDateLabel($date);

            // Calcular máxima y mínima del día
            $temps = array_filter(array_map(fn($h) => $h->temperature, $dayHours), fn($t) => $t !== null);
            $maxTemp = !empty($temps) ? max($temps) : null;
            $minTemp = !empty($temps) ? min($temps) : null;

            $tempRange = '';
            if ($maxTemp !== null && $minTemp !== null) {
                $tempRange = " &mdash; M&iacute;n: {$minTemp}&deg; / M&aacute;x: {$maxTemp}&deg;";
            }

            $html .= "<h3>{$dayLabel}{$tempRange}</h3>\n";
            $html .= "<div class=\"forecast-scroll\"><table class=\"forecast\">\n";
            $html .= "<tr><th>Hora</th><th>&deg;C</th><th>Sens.</th><th>Viento</th><th>Prob.</th><th>Lluvia</th><th>Cielo</th></tr>\n";

            foreach ($dayHours as $h) {
                $hour = substr($h->datetime, 11, 5);
                $temp = $h->temperature !== null ? "{$h->temperature}&deg;" : '-';
                $feels = $h->feelsLike !== null ? "{$h->feelsLike}&deg;" : '-';

                // Viento: dirección + velocidad en km/h
                $windStr = '-';
                if ($h->windDir !== null && $h->windSpeed !== null) {
                    $arrow = $h->windArrow();
                    $windStr = "{$arrow} {$h->windDir} {$h->windSpeed} km/h";
                    if ($h->windGust !== null && $h->windGust > $h->windSpeed) {
                        $windStr .= " <small>(racha {$h->windGust})</small>";
                    }
                }

                // Probabilidad de lluvia
                $rainProb = $h->precipProb !== null ? "{$h->precipProb}%" : '-';

                // Cantidad de lluvia: mostrar solo si hay probabilidad > 0
                $rainAmount = '-';
                if ($h->precipProb !== null && $h->precipProb > 0 && $h->precipAmount !== null && $h->precipAmount !== '0') {
                    $rainAmount = "{$h->precipAmount} mm";
                }

                $sky = $h->skyDescription ? htmlspecialchars($h->skyDescription, ENT_QUOTES, 'UTF-8') : '-';

                // Resaltar lluvia alta
                $probClass = '';
                if ($h->precipProb !== null && $h->precipProb >= 60) {
                    $probClass = ' class="rain-high"';
                } elseif ($h->precipProb !== null && $h->precipProb >= 30) {
                    $probClass = ' class="rain-med"';
                }

                $html .= "<tr>";
                $html .= "<td><strong>{$hour}</strong></td>";
                $html .= "<td>{$temp}</td>";
                $html .= "<td>{$feels}</td>";
                $html .= "<td>{$windStr}</td>";
                $html .= "<td{$probClass}>{$rainProb}</td>";
                $html .= "<td>{$rainAmount}</td>";
                $html .= "<td>{$sky}</td>";
                $html .= "</tr>\n";
            }

            $html .= "</table></div>\n";
        }

        return $html;
    }

    /**
     * Formatear fecha como "Hoy, 9 de febrero" o "Mañana, 10 de febrero"
     */
    private static function formatDateLabel(string $date): string {
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $parts = explode('-', $date);
        $day = (int)$parts[2];
        $month = $months[(int)$parts[1]] ?? '';

        $prefix = '';
        if ($date === $today) {
            $prefix = 'Hoy, ';
        } elseif ($date === $tomorrow) {
            $prefix = 'Ma&ntilde;ana, ';
        }

        return "{$prefix}{$day} de {$month}";
    }

    /**
     * Generar filtro de provincias permitidas
     * Siempre muestra las provincias configuradas en ALLOWED_PROVINCES
     */
    public static function renderProvinceFilter(): string {
        if (empty(self::ALLOWED_PROVINCES)) {
            return '';
        }

        $html = "<div class=\"prov-filter\">\n<strong>Filtrar por provincia:</strong><br>\n";
        $html .= "<a href=\"#\" onclick=\"fp('');return false\" data-prov=\"\" style=\"font-weight:bold\">Todas</a>";

        foreach (self::ALLOWED_PROVINCES as $prov) {
            $provSafe = htmlspecialchars($prov, ENT_QUOTES, 'UTF-8');
            $html .= " | <a href=\"#\" onclick=\"fp('{$provSafe}');return false\" data-prov=\"{$provSafe}\">{$provSafe}</a>";
        }

        $html .= "\n</div>\n";
        return $html;
    }

    /**
     * Generar filtro de municipios de Cádiz
     */
    public static function renderMunicipalityFilter(): string {
        if (empty(self::CADIZ_MUNICIPALITIES)) {
            return '';
        }

        $html = "<div class=\"munic-filter\">\n<strong>Filtrar por municipio (Cádiz):</strong><br>\n";
        $html .= "<a href=\"#\" onclick=\"fm('');return false\" data-munic=\"\" style=\"font-weight:bold\">Todos</a>";

        foreach (self::CADIZ_MUNICIPALITIES as $munic) {
            $municSafe = htmlspecialchars($munic, ENT_QUOTES, 'UTF-8');
            $html .= " | <a href=\"#\" onclick=\"fm('{$municSafe}');return false\" data-munic=\"{$municSafe}\">{$municSafe}</a>";
        }

        $html .= "\n</div>\n";
        return $html;
    }

    /**
     * Renderizar sección de alertas como HTML, agrupadas por fuente
     */
    public static function renderAlertsSection(array $alerts): string {
        if (empty($alerts)) {
            return <<<HTML
<p><strong>No hay alertas activas en este momento.</strong></p>
<p>Consulta las fuentes oficiales para información en tiempo real.</p>
HTML;
        }

        $grouped = self::groupBySource($alerts);
        $html = "";

        foreach ($grouped as $source => $sourceAlerts) {
            $label = htmlspecialchars(
                self::SOURCE_LABELS[$source] ?? strtoupper($source),
                ENT_QUOTES,
                'UTF-8'
            );
            $html .= "<div class=\"src-group\">\n<h3>{$label}</h3>\n<ul>\n";

            foreach ($sourceAlerts as $alert) {
                $emoji = self::SEVERITY_EMOJI[$alert->severity] ?? "";
                $headline = htmlspecialchars(
                    $alert->headline ?: $alert->description,
                    ENT_QUOTES,
                    'UTF-8'
                );

                // Provincias para el filtro
                $provinces = $alert->area ? self::extractProvinces($alert->area) : [];
                $provAttr = htmlspecialchars(implode(',', $provinces), ENT_QUOTES, 'UTF-8');

                // Mostrar zona afectada
                $areaHtml = '';
                if ($alert->area) {
                    $areaText = htmlspecialchars($alert->area, ENT_QUOTES, 'UTF-8');
                    $areaHtml = "<br><small>Zona: {$areaText}</small>";
                }

                // Detectar municipio si está en el área
                $municipalities = '';
                if ($alert->area) {
                    $detectedMunics = self::extractMunicipalities($alert->area);
                    $municipalities = htmlspecialchars(implode(',', $detectedMunics), ENT_QUOTES, 'UTF-8');
                }

                $html .= sprintf(
                    "<li class=\"al\" data-provinces=\"%s\" data-municipalities=\"%s\">%s <strong>%s</strong>%s</li>\n",
                    $provAttr,
                    $municipalities,
                    $emoji,
                    $headline,
                    $areaHtml
                );
            }

            $html .= "</ul>\n</div>\n";
        }

        return $html;
    }

    /**
     * Generar página HTML completa
     */
    public static function renderHTML(array $alerts, array $forecast = []): string {
        $now = date('Y-m-d H:i') . ' UTC';
        $filterHtml = self::renderProvinceFilter();
        $municFilterHtml = self::renderMunicipalityFilter();
        $alertsHtml = self::renderAlertsSection($alerts);
        $weatherHtml = !empty($forecast) ? self::renderWeatherSection($forecast) : '';

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
.prov-filter { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; line-height: 2; }
.prov-filter a { white-space: nowrap; }
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

<h1>ChipiTiempo</h1>

<p><em>Actualizado: $now</em></p>

$weatherHtml

<hr>

<h2>ALERTAS Y AVISOS ACTIVOS</h2>

$filterHtml
$municFilterHtml
$alertsHtml

<p><strong>Fuente oficial:</strong></p>
<ul>
<li><strong>AEMET</strong> - Alertas meteorol&oacute;gicas: <a href="https://www.aemet.es/es/eltiempo/prediccion/avisos">aemet.es/avisos</a></li>
</ul>

<hr>

<h2>LLAMADAS DE EMERGENCIA</h2>

<h3>Emergencias Generales</h3>
<ul>
<li><strong>112</strong> - Emergencias (Polic&iacute;a, Bomberos, Ambulancia) - <a href="tel:112">Llamar</a></li>
<li><strong>091</strong> - Polic&iacute;a Nacional - <a href="tel:091">Llamar</a></li>
<li><strong>062</strong> - Guardia Civil - <a href="tel:062">Llamar</a></li>
<li><strong>080/085</strong> - Bomberos (var&iacute;a por comunidad) - <a href="tel:080">Llamar</a></li>
<li><strong>061</strong> - Urgencias Sanitarias - <a href="tel:061">Llamar</a></li>
</ul>

<h3>Emergencias Especializadas</h3>
<ul>
<li><strong>016</strong> - Violencia de G&eacute;nero (no deja rastro en factura) - <a href="tel:016">Llamar</a></li>
<li><strong>024</strong> - Atenci&oacute;n a la Conducta Suicida - <a href="tel:024">Llamar</a></li>
</ul>

<hr>

<details>
<summary><strong>GU&Iacute;AS R&Aacute;PIDAS DE ACTUACI&Oacute;N</strong></summary>

<h3>DANA / INUNDACIONES</h3>
<ul>
<li>NO cruces zonas inundadas a pie ni en veh&iacute;culo</li>
<li>Al&eacute;jate de barrancos, ramblas y cauces secos</li>
<li>Si est&aacute;s en un veh&iacute;culo y empieza a flotar, ABAND&Oacute;NALO</li>
<li>Busca terreno elevado</li>
<li>NO bajes a s&oacute;tanos o garajes</li>
<li>Corta la electricidad si hay agua en casa</li>
</ul>

<h3>INCENDIOS FORESTALES</h3>
<ul>
<li>Llama al 112 inmediatamente</li>
<li>Evac&uacute;a si las autoridades lo ordenan - NO ESPERES</li>
<li>NO huyas monte arriba, el fuego sube m&aacute;s r&aacute;pido</li>
</ul>

<h3>OLAS DE CALOR</h3>
<ul>
<li>Hidr&aacute;tate constantemente</li>
<li>Evita salir entre 12h y 17h</li>
<li>Vigila a ancianos, ni&ntilde;os y enfermos cr&oacute;nicos</li>
<li>Golpe de calor: llama al 112 y enfr&iacute;a el cuerpo con agua</li>
</ul>

<h3>TORMENTAS Y VIENTO FUERTE</h3>
<ul>
<li>Busca refugio en edificios s&oacute;lidos</li>
<li>Al&eacute;jate de &aacute;rboles, postes y estructuras altas</li>
<li>Evita usar tel&eacute;fonos con cable durante tormentas el&eacute;ctricas</li>
<li>NO te refugies bajo &aacute;rboles aislados</li>
</ul>
</details>

<hr>

<p><small>ChipiTiempo - Datos de <a href="https://www.aemet.es">AEMET</a>. No sustituye los servicios oficiales. En caso de emergencia, llama al <strong>112</strong>.</small></p>

<script>
var currentProvince = '';
var currentMunicipality = '';

function updateAlertsDisplay() {
    var items = document.querySelectorAll('.al');
    for (var i = 0; i < items.length; i++) {
        var p = ',' + items[i].getAttribute('data-provinces') + ',';
        var m = ',' + items[i].getAttribute('data-municipalities') + ',';

        var provMatch = !currentProvince || p.indexOf(',' + currentProvince + ',') >= 0;
        var municMatch = !currentMunicipality || m.indexOf(',' + currentMunicipality + ',') >= 0;

        items[i].style.display = (provMatch && municMatch) ? '' : 'none';
    }
    var groups = document.querySelectorAll('.src-group');
    for (var i = 0; i < groups.length; i++) {
        var els = groups[i].querySelectorAll('.al');
        var any = false;
        for (var j = 0; j < els.length; j++) {
            if (els[j].style.display !== 'none') { any = true; break; }
        }
        groups[i].style.display = any ? '' : 'none';
    }
}

function fp(prov) {
    currentProvince = prov;
    updateAlertsDisplay();
    var links = document.querySelectorAll('.prov-filter a');
    for (var i = 0; i < links.length; i++) {
        links[i].style.fontWeight = (links[i].getAttribute('data-prov') === (prov || '')) ? 'bold' : 'normal';
    }
}

function fm(munic) {
    currentMunicipality = munic;
    updateAlertsDisplay();
    var links = document.querySelectorAll('.munic-filter a');
    for (var i = 0; i < links.length; i++) {
        links[i].style.fontWeight = (links[i].getAttribute('data-munic') === (munic || '')) ? 'bold' : 'normal';
    }
}

// Inicializar con Chipiona por defecto
document.addEventListener('DOMContentLoaded', function() {
    fm('Chipiona');
});
</script>

</body>
</html>
HTML;
    }
}
