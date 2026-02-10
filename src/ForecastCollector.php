<?php

/**
 * ChipiTiempo - Colector de previsión meteorológica
 * 
 * Responsabilidad única: coleccionar datos de previsión
 */

require_once __DIR__ . '/Sources/AEMET.php';
require_once __DIR__ . '/Config/AppConfig.php';

use ChipiTiempo\Config\AppConfig;
use ChipiTiempo\Sources\AEMET as AEMETSource;

class ForecastCollector {
    
    /**
     * Obtener previsión para un municipio (horaria + diaria)
     * 
     * @param string|null $municipality Nombre del municipio (default: Chipiona)
     * @return array Datos de previsión con estructura {name, province, issued, hours, daily_hours}
     */
    public static function collect(?string $municipality = null): array {
        $municipality = $municipality ?? AppConfig::DEFAULT_MUNICIPALITY;
        $code = AppConfig::getMunicipalityCode($municipality);
        
        if (!$code) {
            echo "[ForecastCollector] Unknown municipality: {$municipality}\n";
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => [], 'daily_hours' => []];
        }
        
        try {
            // Obtener previsión horaria (3 días)
            $forecast = AEMETSource::fetchHourlyForecast($code);
            $count = count($forecast['hours'] ?? []);
            echo "[ForecastCollector] {$count} horas de previsión para {$municipality}\n";
            
            // Normalizar el nombre: usar el nombre solicitado en lugar del devuelto por AEMET
            $forecast['name'] = $municipality;
            
            // Obtener previsión diaria (días 4+)
            $dailyForecast = AEMETSource::fetchDailyForecast($code);
            $dailyCount = count($dailyForecast['days'] ?? []);
            
            // Filtrar días que ya están cubiertos por la previsión horaria
            // La previsión horaria suele tener 3 días (hoy, mañana, pasado mañana)
            $lastHourlyDate = '';
            if (!empty($forecast['hours'])) {
                $lastHour = end($forecast['hours']);
                $lastHourlyDate = substr($lastHour->datetime, 0, 10);
            }
            
            $dailyHours = [];
            if (!empty($dailyForecast['days'])) {
                foreach ($dailyForecast['days'] as $day) {
                    // Solo incluir días posteriores a la previsión horaria
                    if ($day->date > $lastHourlyDate) {
                        $dailyHours[] = $day;
                    }
                }
            }
            
            $forecast['daily_hours'] = $dailyHours;
            
            if (!empty($dailyHours)) {
                echo "[ForecastCollector] " . count($dailyHours) . " días adicionales de previsión diaria para {$municipality}\n";
            }
            
            return $forecast;
        } catch (Exception $exc) {
            echo "[ForecastCollector] Error: {$exc->getMessage()}\n";
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => [], 'daily_hours' => []];
        }
    }

    /**
     * Obtener previsiones para múltiples municipios principales
     * 
     * @return array Array de previsiones por municipio
     */
    public static function collectMultiple(): array {
        $municipalities = [
            'Chipiona',
            'Rota',
            'Sanlúcar de Barrameda',
            'Jerez de la Frontera',
            'Cádiz (capital)',
        ];

        $forecasts = [];
        foreach ($municipalities as $municipality) {
            $forecast = self::collect($municipality);
            if (!empty($forecast['hours'])) {
                // Usar el nombre solicitado como clave para mantener consistencia
                // (aunque AEMET devuelva un nombre diferente en algunos casos)
                $forecasts[$municipality] = $forecast;
            }
        }

        return $forecasts;
    }
}
