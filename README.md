# SypexGeo

[![CI](https://github.com/globus-studio/SypexGeo/actions/workflows/ci.yml/badge.svg)](https://github.com/globus-studio/SypexGeo/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.1-777bb4.svg)](composer.json)

Maintained PHP 8 reader for the SypexGeo binary IP-to-geo database. Pure PHP, zero runtime dependencies, exposing a modern PSR-4 namespaced class.

Maintained by [GLOBUS.studio](https://globus.studio) and Yevhen Leonidov.

## Features

- Pure PHP reader for SypexGeo Country, City and City Max databases.
- Compatible with PHP 8.1 through 8.5.
- File, in-memory and batch lookup modes (combinable as bit flags).
- Country, City and full City+Region+Country lookups.
- PHPUnit test suite with 100% line and method coverage of the reader.

## Requirements

- PHP 8.1 or newer.
- A SypexGeo binary database file (`SxGeo.dat`).

## Installation

### Composer

```bash
composer require globus-studio/sypexgeo
```

### Manual

Copy `src/SxGeo.php` into your project and load it however you prefer (PSR-4 autoloader or a plain `require`).

## Usage

```php
use GlobusStudio\SypexGeo\SxGeo;

$sxgeo = new SxGeo(__DIR__ . '/SxGeo.dat', SxGeo::MODE_FILE);

$iso     = $sxgeo->getCountry('8.8.8.8');     // "US"
$id      = $sxgeo->getCountryId('8.8.8.8');   // internal numeric id
$city    = $sxgeo->getCity('8.8.8.8');        // ['city' => ..., 'country' => ...]
$full    = $sxgeo->getCityFull('8.8.8.8');    // ['city' => ..., 'region' => ..., 'country' => ...]
$about   = $sxgeo->about();                   // database metadata
```

### Lookup modes

Modes are bit flags and may be combined.

| Constant                   | Behaviour                                           |
| -------------------------- | --------------------------------------------------- |
| `SxGeo::MODE_FILE`         | Read from disk on every lookup. Lowest memory.      |
| `SxGeo::MODE_MEMORY`       | Load the whole database into RAM. Fastest reads.    |
| `SxGeo::MODE_BATCH`        | Pre-decode indices for many sequential lookups.     |
| `MODE_MEMORY \| MODE_BATCH` | Best throughput for bulk processing.                |

```php
$sxgeo = new SxGeo('SxGeo.dat', SxGeo::MODE_MEMORY | SxGeo::MODE_BATCH);
```

## API summary

| Method                          | Returns                                                         |
| ------------------------------- | --------------------------------------------------------------- |
| `get(string $ip)`               | City structure for City DB, country code for Country DB.        |
| `getCountry(string $ip)`        | ISO 3166-1 alpha-2 country code, empty string for invalid IPs.  |
| `getCountryId(string $ip)`      | Internal numeric country id, `0` for invalid IPs.               |
| `getCity(string $ip)`           | `['city' => ..., 'country' => ...]` or `false`.                 |
| `getCityFull(string $ip)`       | `['city' => ..., 'region' => ..., 'country' => ...]` or `false`.|
| `about()`                       | Database metadata (type, charset, sizes, timestamps).           |

Notes:

- IPv4 only.
- Private and reserved ranges (`10/8`, `127/8`, `0/8`) intentionally return `false` / empty.
- The constructor throws `\RuntimeException` if the database is missing or has an unexpected format.

## Testing

```bash
composer install
composer test
```

The unit suite runs without any database. Integration tests are skipped automatically unless a real database is available. To run them:

1. Place a SypexGeo `.dat` file at `tests/fixtures/SxGeo.dat`, or
2. Set `SXGEO_DB=/absolute/path/to/SxGeo.dat`, or
3. Use the helper:

   ```bash
   composer fetch-db -- https://example.com/SxGeoCity_utf8.zip
   ```

   The script accepts a direct `.dat` URL or a `.zip` archive containing one.

## Continuous integration

GitHub Actions runs the suite against PHP 8.1–8.5 on every push and pull request. Set the `SXGEO_URL` repository secret to a downloadable database URL to enable the integration suite in CI.

## License

MIT, see [LICENSE](LICENSE).

## Credits

Based on the original SypexGeo library by zapimir and BINOVATOR. This fork is maintained by GLOBUS.studio and Yevhen Leonidov.
