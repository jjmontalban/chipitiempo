<?php

namespace ChipiTiempo;

/**
 * AEMETDailyForecast - Pronóstico diario de AEMET
 */
class AEMETDailyForecast {
    public function __construct(
        public string $date,              // YYYY-MM-DD
        public ?int $tempMin = null,      // Temperatura mínima
        public ?int $tempMax = null,      // Temperatura máxima
        public ?string $skyDescription = null,  // Descripción del cielo
        public ?int $precipProb = null,   // Probabilidad de precipitación
        public ?string $windDir = null,   // Dirección del viento (ej: "N", "SO")
        public ?int $windSpeed = null,    // Velocidad del viento en km/h
    ) {
    }

    /**
     * Retornar flecha con dirección del viento
     */
    public function windArrow(): string {
        $arrows = [
            'N' => '↑', 'NE' => '↗', 'E' => '→', 'SE' => '↘',
            'S' => '↓', 'SO' => '↙', 'O' => '←', 'NO' => '↖',
        ];
        return $arrows[$this->windDir] ?? '';
    }
}
