<?php

/**
 * ChipiTiempo - Configuración centralizada
 * 
 * Punto único de verdad para toda la configuración del sistema.
 * Reemplaza constantes dispersas en diferentes clases.
 */

namespace ChipiTiempo\Config;

class AppConfig {
    
    // =========================================================================
    // CONFIGURACIÓN GENÉRICA
    // =========================================================================
    
    /** Municipio por defecto para previsión */
    public const DEFAULT_MUNICIPALITY = 'Chipiona';
    
    // =========================================================================
    // SEVERIDAD DE ALERTAS
    // =========================================================================
    
    /** Emojis para severidades */
    public const SEVERITY_EMOJIS = [
        "red" => "🔴",
        "orange" => "🟠",
        "yellow" => "🟡",
        "green" => "✅",
    ];
    
    // =========================================================================
    // CONFIGURACIÓN GEOGRÁFICA
    // =========================================================================
    
    /** Municipios de Cádiz para filtros locales */
    public const CADIZ_MUNICIPALITIES = [
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
    
    /** Códigos INE de municipios de Cádiz para API AEMET */
    public const MUNICIPALITY_CODES = [
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
    
    // =========================================================================
    // MÉTODOS HELPER
    // =========================================================================
    
    /**
     * Obtener código INE de un municipio
     */
    public static function getMunicipalityCode(string $name): ?string {
        return self::MUNICIPALITY_CODES[$name] ?? null;
    }
    
    /**
     * Obtener flecha Unicode para una dirección del viento
     */
    public static function windArrow(?string $windDir): string {
        return match ($windDir) {
            'N'  => '↑',
            'NE' => '↗',
            'E'  => '→',
            'SE' => '↘',
            'S'  => '↓',
            'SO' => '↙',
            'O'  => '←',
            'NO' => '↖',
            'C'  => '○',
            default => '',
        };
    }
}
