<?php

/**
 * ChipiTiempo - Colector de previsión meteorológica
 * 
 * Responsabilidad única: coleccionar datos de previsión
 */

require_once __DIR__ . '/Sources/AEMET.php';
require_once __DIR__ . '/Config/AppConfig.php';
require_once __DIR__ . '/Logging/Logger.php';

use ChipiTiempo\Config\AppConfig;
use ChipiTiempo\Logging\Logger;
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
            Logger::warning("[ForecastCollector] Unknown municipality: {$municipality}");
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => [], 'daily_hours' => []];
        }
        
        try {
            // Obtener previsión horaria (3 días)
            $forecast = AEMETSource::fetchHourlyForecast($code);
            $count = count($forecast['hours'] ?? []);
            Logger::info("[ForecastCollector] {$count} horas de previsión para {$municipality}");
            
            // Normalizar el nombre: usar el nombre solicitado en lugar del devuelto por AEMET
            $forecast['name'] = $municipality;
            
            // Obtener previsión diaria (días 4+)
            $dailyForecast = AEMETSource::fetchDailyForecast($code);
            $dailyCount = count($dailyForecast['days'] ?? []);
            Logger::debug("[ForecastCollector] {$dailyCount} días en previsión diaria para {$municipality}");
            
            // Incluir en daily_hours solo los días posteriores a mañana (hoy y mañana son horarios)
            $tz = new \DateTimeZone('Europe/Madrid');
            $tomorrowDate = (new \DateTime('tomorrow', $tz))->format('Y-m-d');

            $dailyHours = [];
            if (!empty($dailyForecast['days'])) {
                foreach ($dailyForecast['days'] as $day) {
                    if ($day->date > $tomorrowDate) {
                        $dailyHours[] = $day;
                    }
                }
            }
            
            $forecast['daily_hours'] = $dailyHours;
            
            if (!empty($dailyHours)) {
                Logger::info("[ForecastCollector] " . count($dailyHours) . " días adicionales de previsión diaria para {$municipality}");
            }
            
            return $forecast;
        } catch (Exception $exc) {
            Logger::error("[ForecastCollector] Error: {$exc->getMessage()}");
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => [], 'daily_hours' => []];
        }
    }

    /**
     * Obtener previsión solo diaria para un municipio (sin horaria)
     */
    public static function collectDailyOnly(string $municipality): array {
        $code = AppConfig::getMunicipalityCode($municipality);
        if (!$code) {
            Logger::warning("[ForecastCollector] Unknown municipality: {$municipality}");
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => [], 'daily_hours' => [], 'display_mode' => 'daily_only'];
        }

        try {
            $dailyForecast = AEMETSource::fetchDailyForecast($code);
            $tz = new \DateTimeZone('Europe/Madrid');
            $today = (new \DateTime('today', $tz))->format('Y-m-d');

            $dailyHours = [];
            foreach ($dailyForecast['days'] ?? [] as $day) {
                if ($day->date >= $today) {
                    $dailyHours[] = $day;
                }
            }

            Logger::info("[ForecastCollector] " . count($dailyHours) . " días de previsión diaria para {$municipality}");

            return [
                'name' => $municipality,
                'province' => $dailyForecast['province'] ?? '',
                'issued' => $dailyForecast['issued'] ?? '',
                'hours' => [],
                'daily_hours' => $dailyHours,
                'display_mode' => 'daily_only',
            ];
        } catch (\Exception $exc) {
            Logger::error("[ForecastCollector] Error for {$municipality}: {$exc->getMessage()}");
            return ['name' => $municipality, 'province' => '', 'issued' => '', 'hours' => [], 'daily_hours' => [], 'display_mode' => 'daily_only'];
        }
    }

    /**
     * Obtener previsiones para múltiples municipios principales
     *
     * @return array Array de previsiones por municipio
     */
    public static function collectMultiple(): array {
        $forecasts = [];
        foreach (AppConfig::DEFAULT_MUNICIPALITIES as $municipality) {
            if (in_array($municipality, AppConfig::DAILY_ONLY_MUNICIPALITIES)) {
                $forecast = self::collectDailyOnly($municipality);
                if (!empty($forecast['daily_hours'])) {
                    $forecasts[$municipality] = $forecast;
                }
            } else {
                $forecast = self::collect($municipality);
                $forecast['display_mode'] = 'hourly_daily';
                if (!empty($forecast['hours'])) {
                    $forecasts[$municipality] = $forecast;
                }
            }
        }
        return $forecasts;
    }
}
