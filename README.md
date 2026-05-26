# Ticket Scraper

CLI en PHP para extraer y normalizar entradas desde proveedores de tickets.

Identidad del proyecto: marca personal discreta con codename interno Kanto para la CLI y documentacion tecnica.

## Setup

1. Instala dependencias:

```bash
composer install
```

2. Ejecuta pruebas:

```bash
composer test
```

3. Ejecuta scraping de evento SeatGeek:

```bash
php bin/console scrape "https://seatgeek.com/aladdin-tickets/theater/2026-07-15-2-pm/18119434"
```

## Estado actual

- Fase 1 completada: estructura base, contratos, DTOs y pruebas iniciales.
- Fase 2 completada para SeatGeek: consumo de payload JSON embebido (`__NEXT_DATA__`), normalizacion de tickets y salida en tabla agrupada por section/row/price.
- Fase 3 implementada parcialmente para VividSeats: deteccion de dominio, parsing conservador de JSON embebido y error controlado cuando la pagina redirige a tracking o anti-bot.
- Fase 4 cerrada: pruebas de integracion del comando CLI, validacion de salida y documentacion de entrega.

## Limitaciones observadas en ejecucion real

- En este entorno, SeatGeek responde `403 Forbidden` (proteccion anti-bot) al solicitar la URL de evento real.
- La aplicacion lo reporta como `ProviderException` con mensaje claro.
- La logica de extraccion/parsing queda validada con pruebas unitarias usando HTML con payload `__NEXT_DATA__`.
- En VividSeats, el fetch real de la URL ejemplo redirige a `doubleclick.net/activityi`, por lo que la aplicacion lo trata como limitacion externa y devuelve un error de proveedor controlado.
- La parte funcional de VividSeats queda validada por pruebas unitarias con payload simulado.

## Arquitectura breve

- `ScrapeTicketsCommand`: valida URL, selecciona proveedor y renderiza salida.
- `SeatGeekProvider`: descarga HTML, extrae JSON embebido, detecta listados y mapea a `Ticket`.
- `VividSeatsProvider`: intenta extraer JSON embebido con heuristicas conservadoras y detiene la ejecucion si la respuesta es un tracking redirect o no hay payload utilizable.
- `TicketFormatter`: agrupa y ordena por section, row y price.
- `HttpClient`: encapsula peticiones HTTP con timeouts y headers base.

## Verificacion final

- `composer test` ejecuta la suite completa de providers, formatter y comando CLI.
- El comando puede probarse con un provider stub en tests y con URL reales en ejecucion manual.

