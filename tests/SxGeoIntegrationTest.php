<?php

namespace GlobusStudio\SypexGeo\Tests;

use GlobusStudio\SypexGeo\SxGeo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests against a real SypexGeo binary database.
 *
 * The fixture path can be overridden with the SXGEO_DB environment variable.
 * If no database is available the whole suite is skipped instead of failing
 * so contributors without a .dat file can still run `composer test`.
 */
final class SxGeoIntegrationTest extends TestCase
{
    private static ?string $dbPath = null;

    public static function setUpBeforeClass(): void
    {
        $candidates = array_filter([
            getenv('SXGEO_DB') ?: null,
            __DIR__ . '/fixtures/SxGeo.dat',
            __DIR__ . '/fixtures/SxGeoCity.dat',
            __DIR__ . '/fixtures/SxGeoCityMax.dat',
            dirname(__DIR__) . '/SxGeo.dat',
        ]);
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                self::$dbPath = $candidate;

                return;
            }
        }
    }

    protected function setUp(): void
    {
        if (self::$dbPath === null) {
            self::markTestSkipped('No SypexGeo .dat fixture found. Place one at tests/fixtures/SxGeo.dat or set SXGEO_DB.');
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function modeProvider(): iterable
    {
        yield 'file'          => [SxGeo::MODE_FILE];
        yield 'memory'        => [SxGeo::MODE_MEMORY];
        yield 'batch'         => [SxGeo::MODE_BATCH];
        yield 'memory+batch'  => [SxGeo::MODE_MEMORY | SxGeo::MODE_BATCH];
    }

    public function testAboutReturnsExpectedShape(): void
    {
        $sxgeo = new SxGeo(self::$dbPath);
        $about = $sxgeo->about();

        self::assertArrayHasKey('Created', $about);
        self::assertArrayHasKey('Type', $about);
        self::assertArrayHasKey('Charset', $about);
        self::assertArrayHasKey('IP Blocks', $about);
        self::assertGreaterThan(0, $about['IP Blocks']);
        self::assertIsString($about['Type']);
        self::assertNotSame('n/a', $about['Type']);
    }

    #[DataProvider('modeProvider')]
    public function testGetCountryReturnsKnownIsoCodes(int $mode): void
    {
        $sxgeo = new SxGeo(self::$dbPath, $mode);

        self::assertSame('US', $sxgeo->getCountry('8.8.8.8'));
        self::assertSame('US', $sxgeo->getCountry('208.67.222.222'));
        self::assertSame('RU', $sxgeo->getCountry('77.88.8.8'));
    }

    #[DataProvider('modeProvider')]
    public function testGetCountryIdIsConsistentWithIsoTable(int $mode): void
    {
        $sxgeo = new SxGeo(self::$dbPath, $mode);

        $id  = $sxgeo->getCountryId('8.8.8.8');
        $iso = $sxgeo->getCountry('8.8.8.8');

        self::assertGreaterThan(0, $id);
        self::assertSame($sxgeo->id2iso[$id], $iso);
    }

    public function testInvalidAndPrivateAddressesReturnEmpty(): void
    {
        $sxgeo = new SxGeo(self::$dbPath);

        self::assertSame('', $sxgeo->getCountry('not-an-ip'));
        self::assertSame(0, $sxgeo->getCountryId('not-an-ip'));
        self::assertSame('', $sxgeo->getCountry('127.0.0.1'));
        self::assertSame('', $sxgeo->getCountry('10.0.0.1'));
        self::assertSame('', $sxgeo->getCountry('0.0.0.0'));
        self::assertFalse($sxgeo->getCity('127.0.0.1'));
        self::assertFalse($sxgeo->getCityFull('10.0.0.1'));
    }

    public function testGetCityReturnsStructuredData(): void
    {
        $sxgeo  = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY);
        $about  = $sxgeo->about();
        if (($about['City']['Max Length'] ?? 0) === 0) {
            self::markTestSkipped('Country-only database: getCity() not exercised.');
        }

        $result = $sxgeo->getCity('8.8.8.8');
        self::assertIsArray($result);
        self::assertArrayHasKey('city', $result);
        self::assertArrayHasKey('country', $result);
        self::assertArrayHasKey('iso', $result['country']);
        self::assertSame('US', $result['country']['iso']);
    }

    public function testGetCityFullIncludesRegion(): void
    {
        $sxgeo = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY);
        $about = $sxgeo->about();
        if (($about['City']['Max Length'] ?? 0) === 0 || ($about['Region']['Max Length'] ?? 0) === 0) {
            self::markTestSkipped('Database does not contain city/region data.');
        }

        $result = $sxgeo->getCityFull('8.8.8.8');
        self::assertIsArray($result);
        self::assertArrayHasKey('city', $result);
        self::assertArrayHasKey('region', $result);
        self::assertArrayHasKey('country', $result);
    }

    /**
     * Modes must produce identical results for the same lookup.
     */
    public function testAllModesAgreeOnLookups(): void
    {
        $ips = ['8.8.8.8', '1.1.1.1', '77.88.8.8', '208.67.222.222'];

        $reference = new SxGeo(self::$dbPath, SxGeo::MODE_FILE);
        $batched   = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY | SxGeo::MODE_BATCH);

        foreach ($ips as $ip) {
            self::assertSame(
                $reference->getCountry($ip),
                $batched->getCountry($ip),
                "Mode mismatch for {$ip}"
            );
            self::assertSame(
                $reference->getCountryId($ip),
                $batched->getCountryId($ip),
                "Mode ID mismatch for {$ip}"
            );
        }
    }
}
