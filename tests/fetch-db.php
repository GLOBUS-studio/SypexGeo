<?php

/*
 * Helper script that downloads a SypexGeo database into tests/fixtures/.
 *
 * Usage:
 *   composer fetch-db
 *   composer fetch-db -- https://example.com/SxGeoCity_utf8.zip
 *
 * The script accepts either a direct .dat URL or a .zip archive that
 * contains a single .dat file. Existing fixtures are left untouched
 * unless --force is passed.
 */

$fixtureDir = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';
$target     = $fixtureDir . DIRECTORY_SEPARATOR . 'SxGeo.dat';

if (!is_dir($fixtureDir) && !mkdir($fixtureDir, 0777, true) && !is_dir($fixtureDir)) {
    fwrite(STDERR, "Cannot create fixtures directory.\n");
    exit(1);
}

$args  = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$args  = array_values(array_filter($args, static fn ($a) => $a !== '--force'));

if (file_exists($target) && !$force) {
    fwrite(STDOUT, "Database already exists: {$target}\n");
    fwrite(STDOUT, "Pass --force to overwrite.\n");
    exit(0);
}

$candidates = $args !== [] ? $args : [
    getenv('SXGEO_URL') ?: '',
];
$candidates = array_values(array_filter($candidates, static fn ($u) => $u !== ''));

if ($candidates === []) {
    fwrite(STDERR, "No URL provided. Pass a URL as the first argument or set the SXGEO_URL env var.\n");
    fwrite(STDERR, "The file must be a SypexGeo .dat database or a .zip containing one.\n");
    exit(1);
}

foreach ($candidates as $url) {
    fwrite(STDOUT, "Downloading {$url}...\n");
    $payload = @file_get_contents($url);
    if ($payload === false) {
        fwrite(STDERR, "  failed\n");
        continue;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'sxgeo_');
    file_put_contents($tmp, $payload);

    if (str_ends_with(strtolower($url), '.zip') || substr($payload, 0, 2) === 'PK') {
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            fwrite(STDERR, "  cannot open zip\n");
            unlink($tmp);
            continue;
        }
        $extracted = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.dat')) {
                file_put_contents($target, $zip->getFromIndex($i));
                $extracted = true;
                break;
            }
        }
        $zip->close();
        unlink($tmp);
        if (!$extracted) {
            fwrite(STDERR, "  archive contains no .dat file\n");
            continue;
        }
    } else {
        rename($tmp, $target);
    }

    if (substr((string) file_get_contents($target, false, null, 0, 3), 0, 3) !== 'SxG') {
        fwrite(STDERR, "  downloaded file is not a valid SypexGeo database\n");
        unlink($target);
        continue;
    }

    fwrite(STDOUT, "Saved to {$target}\n");
    exit(0);
}

fwrite(STDERR, "All sources failed.\n");
exit(1);
