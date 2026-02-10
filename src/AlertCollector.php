<?php

/**
 * ChipiTiempo - Colector de alertas
 * 
 * Responsabilidad única: coleccionar alertas de AEMET
 */

require_once __DIR__ . '/Sources/AEMET.php';
require_once __DIR__ . '/Config/AppConfig.php';

use ChipiTiempo\Config\AppConfig;
use ChipiTiempo\Sources\AEMET as AEMETSource;

class AlertCollector {
    
    /**
     * Coleccionar alertas de AEMET
     * 
     * @return Alert[] Array de alertas
     */
    public static function collect(): array {
        $alerts = AEMETSource::fetch();
        echo "[AlertCollector] " . count($alerts) . " alertas de AEMET\n";
        return $alerts;
    }
    
    /**
     * Filtrar alertas por severidad
     */
    public static function filterBySeverity(array $alerts, string $severity): array {
        return array_filter($alerts, fn(Alert $a) => $a->severity === $severity);
    }
    
    /**
     * Filtrar alertas por provincia
     */
    public static function filterByProvince(array $alerts, string $province): array {
        return array_filter($alerts, function (Alert $a) {
            if (!$a->area) return false;
            $provinces = AlertGenerator::extractProvinces($a->area);
            return in_array($province, $provinces);
        });
    }
    
    /**
     * Agrupar alertas por fuente
     */
    public static function groupBySource(array $alerts): array {
        $grouped = [];
        foreach ($alerts as $alert) {
            if (!isset($grouped[$alert->source])) {
                $grouped[$alert->source] = [];
            }
            $grouped[$alert->source][] = $alert;
        }
        return $grouped;
    }
    
    /**
     * Agrupar alertas por severidad
     */
    public static function groupBySeverity(array $alerts): array {
        $grouped = [];
        foreach ($alerts as $alert) {
            if (!isset($grouped[$alert->severity])) {
                $grouped[$alert->severity] = [];
            }
            $grouped[$alert->severity][] = $alert;
        }
        
        // Ordenar por severidad (rojo primero)
        $order = AppConfig::SEVERITY_ORDER;
        uksort($grouped, fn($a, $b) => $order[$a] <=> $order[$b]);
        
        return $grouped;
    }
}
