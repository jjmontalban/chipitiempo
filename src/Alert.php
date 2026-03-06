<?php

namespace ChipiTiempo;

/**
 * AUXIO - Esquema normalizado de alertas
 * 
 * Clase que representa una alerta de emergencia normalizada
 * desde cualquier fuente (AEMET, IGN, etc)
 */

class Alert {
    public string $source;        // "aemet", "ign", etc
    public string $severity;      // "red", "orange", "yellow", "green"
    public string $headline;
    public string $description;
    public ?string $area = null;
    public ?string $event_type = null;
    public ?string $onset = null;
    public ?string $expires = null;
    public ?string $certainty = null;
    public ?string $urgency = null;
    public ?string $sender = null;
    public ?string $web = null;

    public function __construct(
        string $source,
        string $severity,
        string $headline,
        string $description,
        ?string $area = null,
        ?string $event_type = null,
        ?string $onset = null,
        ?string $expires = null,
        ?string $certainty = null,
        ?string $urgency = null,
        ?string $sender = null,
        ?string $web = null
    ) {
        $this->source = $source;
        $this->severity = $severity;
        $this->headline = $headline;
        $this->description = $description;
        $this->area = $area;
        $this->event_type = $event_type;
        $this->onset = $onset;
        $this->expires = $expires;
        $this->certainty = $certainty;
        $this->urgency = $urgency;
        $this->sender = $sender;
        $this->web = $web;
    }
}
