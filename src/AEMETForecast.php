<?php

namespace ChipiTiempo;

/**
 * ChipiTiempo - Modelo de datos para previsión meteorológica AEMET horaria
 */

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

    public function toArray(): array {
        return [
            'datetime' => $this->datetime,
            'temperature' => $this->temperature,
            'feels_like' => $this->feelsLike,
            'humidity' => $this->humidity,
            'precip_prob' => $this->precipProb,
            'precip_amount' => $this->precipAmount,
            'wind_dir' => $this->windDir,
            'wind_speed' => $this->windSpeed,
            'wind_gust' => $this->windGust,
            'sky_description' => $this->skyDescription,
            'sky_code' => $this->skyCode,
        ];
    }

    /**
     * Obtener flecha Unicode para la dirección del viento
     */
    public function windArrow(): string {
        return match ($this->windDir) {
            'N'  => "\u{2191}",  // ↑
            'NE' => "\u{2197}",  // ↗
            'E'  => "\u{2192}",  // →
            'SE' => "\u{2198}",  // ↘
            'S'  => "\u{2193}",  // ↓
            'SO' => "\u{2199}",  // ↙
            'O'  => "\u{2190}",  // ←
            'NO' => "\u{2196}",  // ↖
            'C'  => "\u{25CB}",  // ○ (calma)
            default => '',
        };
    }
}
