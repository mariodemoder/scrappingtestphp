# Ticket Scraper (Prueba Tecnica)

CLI en PHP para extraer y normalizar entradas desde proveedores de tickets.

## Setup inicial (Fase 1)

1. Instala dependencias:

```bash
composer install
```

2. Ejecuta pruebas:

```bash
composer test
```

3. Ejecuta comando CLI base:

```bash
php bin/console scrape "https://example.com/event"
```

La integracion de proveedores reales (SeatGeek y VividSeats) se desarrolla en la fase 2.
