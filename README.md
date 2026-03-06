# ChipiTiempo

Generador de página del tiempo para Chipiona y la comarca de Cádiz (España), usando datos oficiales de la [API AEMET OpenData](https://opendata.aemet.es/).

## ¿Qué hace?

- Obtiene la **previsión horaria** (3 días) y **diaria** (días adicionales) para los municipios principales de la provincia de Cádiz.
- Descarga las **alertas meteorológicas** activas en Andalucía.
- Genera un único fichero **`index.html`** ultraligero y sin dependencias externas.

## Requisitos

- PHP 8.1 o superior
- Extensión `phar` habilitada (para descomprimir archivos TAR de alertas)
- Clave de API de AEMET OpenData (gratuita en [opendata.aemet.es](https://opendata.aemet.es/centrodedescargas/inicio))

## Configuración

1. Crea el fichero `.env` en la raíz del proyecto (no se incluye en el repositorio):

```
AEMET_API_KEY=tu_clave_api_aqui
```

2. (Opcional) Ajusta los municipios mostrados por defecto en `src/Config/AppConfig.php`:

```php
public const DEFAULT_MUNICIPALITIES = [
    'Chipiona',
    'Rota',
    'Sanlúcar de Barrameda',
    'Jerez de la Frontera',
    'Cádiz (capital)',
];
```

## Uso

```bash
# Genera index.html en el directorio actual
php generate.php

# Genera en una ruta personalizada
php generate.php /var/www/html/index.html
```

## Despliegue automático

El repositorio incluye un workflow de GitHub Actions (`.github/workflows/deploy.yml`) que despliega automáticamente al servidor de producción en cada push a la rama `prod`.

Las credenciales necesarias (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KEY`, `DEPLOY_DIR`) se configuran como secretos en el repositorio de GitHub.

## Estructura del proyecto

```
generate.php              # Punto de entrada
src/
  Config/AppConfig.php    # Configuración centralizada (municipios, severidades, etc.)
  Logging/Logger.php      # Sistema de logging (consola + archivo)
  Sources/AEMET.php       # Cliente API AEMET (alertas y previsiones)
  Alert.php               # Modelo de datos: alerta
  AEMETForecast.php       # Modelo de datos: previsión horaria
  AEMETDailyForecast.php  # Modelo de datos: previsión diaria
  AlertCollector.php      # Colector de alertas
  ForecastCollector.php   # Colector de previsiones
  Cache.php               # Caché en disco (TTL 5 minutos)
  HtmlBuilder.php         # Generador de HTML
logs/                     # Logs de ejecución (excluidos del repositorio)
```

## Fuentes de datos

- **AEMET OpenData** — previsiones y alertas meteorológicas oficiales de España.
