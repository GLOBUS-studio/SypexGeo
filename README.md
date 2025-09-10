# SypexGeo (PHP 8 compatible)

A maintained fork of the classic SypexGeo PHP reader, updated to work with PHP 8.x. It provides fast IP-to-geo lookup using the SypexGeo binary database (.dat) without external dependencies.

Maintained by GLOBUS.studio and Yevhen Leonidov.

## Features
- Pure PHP reader for the SypexGeo database
- PHP 8.0+ compatible (tested on 8.0–8.3)
- File, memory, and batch modes for optimal performance
- Country and City lookups with optional full details (city, region, country)
- No external services required

## Requirements
- PHP 8.0 or higher
- SypexGeo database file (e.g., `SxGeo.dat`)

## Installation
1. Copy `SypexGeo.php` and your `SxGeo.dat` database file into your project.
2. Include the class and instantiate it in your code.

## Quick start
```php
require __DIR__ . '/SypexGeo.php';

// Modes: SXGEO_FILE (default), SXGEO_MEMORY, SXGEO_BATCH (bit flags)
$SxGeo = new SxGeo('SxGeo.dat', SXGEO_FILE);

$iso = $SxGeo->getCountry('8.8.8.8');        // e.g. "US"
$id  = $SxGeo->getCountryId('8.8.8.8');      // internal country ID
$city = $SxGeo->getCity('8.8.8.8');          // [ 'city' => ..., 'country' => ... ]
$full = $SxGeo->getCityFull('8.8.8.8');      // [ 'city' => ..., 'region' => ..., 'country' => ... ]

$meta = $SxGeo->about();                     // database metadata
```

### Using memory + batch mode
```php
$SxGeo = new SxGeo('SxGeo.dat', SXGEO_MEMORY | SXGEO_BATCH);
// MEMORY keeps DB in RAM for fastest lookups; BATCH optimizes index searches.
```

## API summary
- `get(string $ip)`: Returns city structure if city DB is present, otherwise country code.
- `getCountry(string $ip)`: ISO 3166-1 alpha-2 country code (e.g., "US").
- `getCountryId(string $ip)`: Internal numeric country ID.
- `getCity(string $ip)`: Array with keys `city` and `country`.
- `getCityFull(string $ip)`: Array with `city`, `region`, and `country`.
- `about()`: Array with database metadata (type, charset, sizes, etc.).

## Notes
- IPv4 only. Private/reserved ranges return `false`.
- Make sure your `SxGeo.dat` matches the reader (Country/City/City Max variants).
- Keep the database up to date. See the official SypexGeo resources for the latest databases.

## License
MIT License — see the LICENSE file.

## Authors & maintenance
- GLOBUS.studio
- Yevhen Leonidov

Credits to the original SypexGeo project and its authors.
