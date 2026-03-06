<?php

/**
 * ChipiTiempo - Colector de alertas
 * 
 * Responsabilidad única: coleccionar alertas de AEMET
 */

require_once __DIR__ . '/Sources/AEMET.php';

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
}
