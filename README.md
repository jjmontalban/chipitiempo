# ChipiTiempo

Sistema de previsión meteorológica y alertas para Chipiona y comarca, utilizando datos oficiales de AEMET.

## Características

- **Previsión horaria por municipio**: Consulta la predicción meteorológica hora a hora de municipios de la provincia de Cádiz
- **Alertas meteorológicas**: Visualiza avisos oficiales de AEMET con niveles de severidad (rojo, naranja, amarillo)
- **Filtrado inteligente**: Filtra alertas por provincia y municipio
- **API REST**: Endpoints JSON para integración con otras aplicaciones
- **HTML estático**: Generador de página web ultraligera sin dependencias

## Requisitos

- PHP 8.0 o superior
- Clave API de AEMET (gratuita en [opendata.aemet.es](https://opendata.aemet.es))

## Configuración

1. Crea un archivo `.env` en la raíz del proyecto:
```
AEMET_API_KEY=tu_clave_api_aqui
```

2. Obtén tu clave API gratuita registrándote en [AEMET OpenData](https://opendata.aemet.es/centrodedescargas/altaUsuario)

## Uso

### Generar página HTML

```bash
php generate.php [archivo_salida.html]
```

Ejemplo:
```bash
php generate.php index.html
```

### API REST

#### Previsión horaria
```
GET /api.php?action=forecast&municipality=Chipiona
```

#### Alertas meteorológicas
```
GET /api.php?action=alerts
GET /api.php?action=alerts&severity=red
```

#### Estado del servicio
```
GET /api.php?action=health
```

## Estructura del proyecto

```
chipitiempo/
├── api.php                 # API REST
├── generate.php            # Generador de HTML
├── src/
│   ├── Alert.php          # Modelo de alerta
│   ├── HourlyForecast.php # Modelo de previsión horaria
│   ├── Generator.php      # Lógica de generación y renderizado
│   └── Sources/
│       └── AEMET.php      # Integración con AEMET OpenData
└── .env                   # Configuración (clave API)
```

## Municipios soportados

La aplicación soporta los siguientes municipios de la provincia de Cádiz:

- Algeciras, Arcos de la Frontera, Barbate, Cádiz, Chiclana de la Frontera
- Chipiona, Conil de la Frontera, El Puerto de Santa María, Espera, Grazalema
- Jerez de la Frontera, Jimena de la Frontera, La Línea de la Concepción
- Los Barrios, Medina-Sidonia, Olvera, Prado del Rey, Puerto Real, Rota
- San Fernando, San Roque, Sanlúcar de Barrameda, Tarifa, Ubrique, Vejer de la Frontera

## Fuente de datos

Este proyecto utiliza exclusivamente datos oficiales de **AEMET** (Agencia Estatal de Meteorología):

- **Previsión horaria**: Predicción meteorológica oficial por municipio
- **Alertas meteorológicas**: Avisos en formato CAP (Common Alerting Protocol)

## Licencia

Este proyecto es de código abierto. Los datos meteorológicos son propiedad de AEMET y están sujetos a sus términos de uso.

## Avisos legales

- Este servicio no sustituye a las fuentes oficiales
- En caso de emergencia, llama siempre al **112**
- Consulta [aemet.es](https://www.aemet.es) para información oficial actualizada