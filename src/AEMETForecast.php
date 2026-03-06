<?php

namespace ChipiTiempo;

/**
 * ChipiTiempo - Modelo de datos para previsión meteorológica AEMET horaria
 */

require_once __DIR__ . '/Config/AppConfig.php';

use ChipiTiempo\Config\AppConfig;

class AEMETForecast {
    public string $datetime;        // ISO datetime (fecha + hora)
    public ?int $temperature;       // °C
    public ?int $feelsLike;         // °C (sensación térmica)
    public ?int $humidity;          // % humedad relativa
    public ?int $precipProb;        // % probabilidad de precipitación
    public ?string $precipAmount;   // mm precipitación
    public ?string $windDir;        // N, NE, E, SE, S, SO, O, NO, C
    public ?int $windSpeed;         // km/h
    public ?int $windGust;          // km/h (racha máxima)
    public ?string $skyDescription; // descripción del estado del cielo
    public ?string $skyCode;        // código numérico AEMET

    public function __construct(
        string $datetime,
        ?int $temperature = null,
        ?int $feelsLike = null,
        ?int $humidity = null,
        ?int $precipProb = null,
        ?string $precipAmount = null,
        ?string $windDir = null,
        ?int $windSpeed = null,
        ?int $windGust = null,
        ?string $skyDescription = null,
        ?string $skyCode = null
    ) {
        $this->datetime = $datetime;
        $this->temperature = $temperature;
        $this->feelsLike = $feelsLike;
        $this->humidity = $humidity;
        $this->precipProb = $precipProb;
        $this->precipAmount = $precipAmount;
        $this->windDir = $windDir;
        $this->windSpeed = $windSpeed;
        $this->windGust = $windGust;
        $this->skyDescription = $skyDescription;
        $this->skyCode = $skyCode;
    }

    /**
     * Obtener flecha Unicode para la dirección del viento
     */
    public function windArrow(): string {
        return AppConfig::windArrow($this->windDir);
    }
}
