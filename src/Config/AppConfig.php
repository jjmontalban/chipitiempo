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
    
    /** Timeout en segundos para solicitudes HTTP */
    public const HTTP_TIMEOUT = 30;
    
    /** User-Agent para solicitudes HTTP */
    public const HTTP_USER_AGENT = 'ChipiTiempo/1.0';
    
    // =========================================================================
    // SEVERIDAD DE ALERTAS (Mapeado a colores y textos)
    // =========================================================================
    
    /** Orden de severidades (valor menor = más severo) */
    public const SEVERITY_ORDER = [
        "red" => 0,
        "orange" => 1,
        "yellow" => 2,
        "green" => 3,
    ];
    
    /** Labels en español para severidades */
    public const SEVERITY_LABELS = [
        "red" => "Rojo",
        "orange" => "Naranja",
        "yellow" => "Amarillo",
        "green" => "Verde",
    ];
    
    /** Emojis para severidades */
    public const SEVERITY_EMOJIS = [
        "red" => "🔴",
        "orange" => "🟠",
        "yellow" => "🟡",
        "green" => "✅",
    ];
    
    // =========================================================================
    // FUENTES DE DATOS
    // =========================================================================
    
    /** Labels de fuentes de alertas */
    public const SOURCE_LABELS = [
        "ign" => "Sismología (IGN)",
        "aemet" => "Meteorología (AEMET)",
    ];
    
    /** URL base de API AEMET */
    public const AEMET_BASE_URL = "https://opendata.aemet.es/opendata";
    
    /** Endpoint de alertas AEMET (CAP XML) */
    public const AEMET_ALERTS_ENDPOINT = "/api/avisos_cap/ultimoelaborado/area/{area}";
    
    /** Endpoint de previsión horaria AEMET */
    public const AEMET_HOURLY_ENDPOINT = "/api/prediccion/especifica/municipio/horaria/{municipio}";
    
    /** Feed de sismología IGN */
    public const IGN_FEED_URL = "https://www.ign.es/ign/RssTools/sismologia.xml";
    
    // =========================================================================
    // CONFIGURACIÓN GEOGRÁFICA
    // =========================================================================
    
    /** Provincias permitidas para filtros */
    public const ALLOWED_PROVINCES = [
        'Cádiz',
        'Huelva',
        'Sevilla',
        'Málaga',
    ];
    
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
     * Verificar si una provincia está permitida
     */
    public static function isAllowedProvince(string $province): bool {
        return in_array($province, self::ALLOWED_PROVINCES);
    }
    
    /**
     * Obtener label de severidad
     */
    public static function getSeverityLabel(string $severity): string {
        return self::SEVERITY_LABELS[$severity] ?? 'Desconocida';
    }
    
    /**
     * Obtener emoji de severidad
     */
    public static function getSeverityEmoji(string $severity): string {
        return self::SEVERITY_EMOJIS[$severity] ?? '';
    }
    
    /**
     * Obtener label de fuente
     */
    public static function getSourceLabel(string $source): string {
        return self::SOURCE_LABELS[$source] ?? 'Desconocida';
    }
}
