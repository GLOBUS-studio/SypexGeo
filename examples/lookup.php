<?php

/**
 * SypexGeo usage example.
 *
 * Download a database from https://sypexgeo.net/en/download/
 * and place it in tests/fixtures/, or pass an absolute path.
 */

use GlobusStudio\SypexGeo\SxGeo;

require_once __DIR__ . '/../vendor/autoload.php';

$dbFile = __DIR__ . '/../tests/fixtures/SxGeoCity.dat';

// Modes are bit flags, combinable:
//   SxGeo::MODE_FILE          = read from disk on every lookup
//   SxGeo::MODE_BATCH         = pre-decode indices (faster bulk)
//   SxGeo::MODE_MEMORY        = load everything into RAM (fastest)
//   SxGeo::MODE_MEMORY | SxGeo::MODE_BATCH   = best for batch processing
$SxGeo = new SxGeo($dbFile, SxGeo::MODE_MEMORY | SxGeo::MODE_BATCH);

// --- database info ----------------------------------------------------------
var_export($SxGeo->about());
echo "\n\n";

// --- lookups ----------------------------------------------------------------
$ip = '8.8.8.8';

echo "IP: $ip\n\n";

// ISO 3166-1 alpha-2 country code
echo 'Country:    ' . $SxGeo->getCountry($ip) . "\n";
echo 'Country ID: ' . $SxGeo->getCountryId($ip) . "\n\n";

// Auto-pick: city array for City DB, country string for Country DB
var_export($SxGeo->get($ip));
echo "\n\n";

// City (works on Country DB too – returns country-only data)
var_export($SxGeo->getCity($ip));
echo "\n\n";

// City + region + country (full resolution)
var_export($SxGeo->getCityFull($ip));
echo "\n";
