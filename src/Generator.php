<?php

/**
 * AUXIO - Generador de HTML y manejador de alertas
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
        "ign" => "Sismología (IGN)",
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
     * Recopilar alertas de todas las fuentes
     */
    public static function collectAlerts(): array {
        $sources = [
            ['name' => 'AEMET', 'file' => 'AEMET.php', 'class' => 'AEMETSource'],
            ['name' => 'IGN', 'file' => 'IGN.php', 'class' => 'IGNSource'],
        ];

        $allAlerts = [];

        foreach ($sources as $source) {
            try {
                require_once __DIR__ . "/Sources/{$source['file']}";
                $class = $source['class'];
                $alerts = $class::fetch();
                echo "[{$source['name']}] " . count($alerts) . " alertas obtenidas\n";
                $allAlerts = array_merge($allAlerts, $alerts);
            } catch (Exception $exc) {
                echo "[{$source['name']}] Error: {$exc->getMessage()}\n";
            }
        }

        // Ordenar: más severas primero
        usort($allAlerts, function(Alert $a, Alert $b) {
            $sevA = self::SEVERITY_ORDER[$a->severity] ?? 99;
            $sevB = self::SEVERITY_ORDER[$b->severity] ?? 99;
            return $sevA <=> $sevB;
        });

        return $allAlerts;
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
    public static function renderHTML(array $alerts): string {
        $now = date('Y-m-d H:i') . ' UTC';
        $filterHtml = self::renderProvinceFilter();
        $municFilterHtml = self::renderMunicipalityFilter();
        $alertsHtml = self::renderAlertsSection($alerts);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="CHIPITIEMPO - Información del tiempo para Chipiona">
<title>CHIPITIEMPO - El tiempo en Chipiona</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #333; }
hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
ul { padding-left: 20px; }
a { color: #0066cc; }
small { color: #666; }
.prov-filter { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; line-height: 2; }
.prov-filter a { white-space: nowrap; }
.munic-filter { margin: 10px 0; padding: 10px; background: #f0f8ff; border-radius: 4px; line-height: 2; border-left: 4px solid #0066cc; }
.munic-filter a { white-space: nowrap; }
</style>
</head>
<body>

<h1>CHIPITIEMPO - El tiempo en Chipiona</h1>

<p><strong>Información meteorológica.</strong></p>
<p><em>Fecha act: $now</em></p>

<hr>

<h2>ALERTAS Y AVISOS ACTIVOS</h2>

$filterHtml
$municFilterHtml
$alertsHtml

<p><strong>Consulta las alertas oficiales en tiempo real:</strong></p>
<ul>
<li><strong>AEMET</strong> - Alertas meteorológicas: <a href="https://www.aemet.es/es/eltiempo/prediccion/avisos">aemet.es/avisos</a></li>
<li><strong>Protección Civil</strong>: <a href="https://www.proteccioncivil.es">proteccioncivil.es</a></li>
<li><strong>DGT</strong> - Estado de carreteras: <a href="https://infocar.dgt.es/etraffic/">infocar.dgt.es</a></li>
<li><strong>IGN</strong> - Actividad sísmica: <a href="https://www.ign.es/web/ign/portal/sis-catalogo-terremotos">ign.es/terremotos</a></li>
</ul>

<hr>

<h2>LLAMADAS DE EMERGENCIA</h2>

<h3>Emergencias Generales</h3>
<ul>
<li><strong>112</strong> - Emergencias (Policía, Bomberos, Ambulancia) - <a href="tel:112">Llamar</a></li>
<li><strong>091</strong> - Policía Nacional - <a href="tel:091">Llamar</a></li>
<li><strong>062</strong> - Guardia Civil - <a href="tel:062">Llamar</a></li>
<li><strong>080/085</strong> - Bomberos (varía por comunidad) - <a href="tel:080">Llamar</a></li>
<li><strong>061</strong> - Urgencias Sanitarias - <a href="tel:061">Llamar</a></li>
<li><strong>915 620 420</strong> - Servicio de Información Toxicológica - <a href="tel:915620420">Llamar</a></li>
</ul>

<hr>

<h2>GUÍAS RÁPIDAS DE ACTUACIÓN</h2>

<h3>DANA / INUNDACIONES</h3>
<ul>
<li>NO cruces zonas inundadas a pie ni en vehículo</li>
<li>Alójate de barrancos, ramblas y cauces secos</li>
<li>Si estás en un vehículo y empieza a flotar, ABANDÓNALO</li>
<li>Busca terreno elevado</li>
<li>NO bajes a sótanos o garajes</li>
<li>Corta la electricidad si hay agua en casa</li>
</ul>

<h3>TERREMOTOS</h3>
<ul>
<li>DENTRO: Protégete bajo mesa resistente o marco de puerta</li>
<li>FUERA: Alójate de edificios, cables, farolas</li>
<li>NO uses ascensores</li>
<li>Espera réplicas</li>
<li>Sal de edificios dañados inmediatamente</li>
</ul>

<h3>INCENDIOS FORESTALES</h3>
<ul>
<li>Llama al 112 inmediatamente</li>
<li>Evacúa si las autoridades lo ordenan - NO ESPERES</li>
<li>NO huyas monte arriba, el fuego sube más rápido</li>
<li>Alójate en dirección perpendicular al avance del fuego</li>
</ul>

<h3>OLAS DE CALOR</h3>
<ul>
<li>Hidrátate constantemente</li>
<li>Evita salir entre 12h y 17h</li>
<li>Vigila a ancianos, niños y enfermos crónicos</li>
<li>Golpe de calor: llama al 112 y enfría el cuerpo con agua</li>
</ul>

<hr>

<h2>KIT DE EMERGENCIA BÁSICO (72 horas)</h2>
<ul>
<li>Agua: 3 litros por persona/día</li>
<li>Alimentos no perecederos</li>
<li>Radio portátil y linterna con pilas</li>
<li>Batería externa para móvil</li>
<li>Botiquín básico y medicación personal</li>
<li>Copias de documentos importantes</li>
<li>Efectivo en billetes pequeños</li>
<li>Manta térmica y silbato</li>
</ul>

<hr>

<p><small>AUXIO - Proyecto de código abierto. No sustituye los servicios oficiales de emergencia. En caso de emergencia, llama al <strong>112</strong>.</small></p>

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
