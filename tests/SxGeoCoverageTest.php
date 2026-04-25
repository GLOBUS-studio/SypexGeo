<?php

namespace GlobusStudio\SypexGeo\Tests;

use GlobusStudio\SypexGeo\SxGeo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Coverage-driven tests that exercise edge branches and the protected
 * `unpack()` decoder for every supported field type.
 */
final class SxGeoCoverageTest extends TestCase
{
    private static string $dbPath;

    public static function setUpBeforeClass(): void
    {
        self::$dbPath = __DIR__ . '/fixtures/SxGeoCity.dat';
    }

    protected function setUp(): void
    {
        if (!is_file(self::$dbPath)) {
            self::markTestSkipped('SxGeoCity.dat fixture not available.');
        }
    }

    public function testConstructorResolvesRelativePathAgainstClassDir(): void
    {
        $srcCopy = dirname(__DIR__) . '/src/__sxgeo_test_fixture__.dat';
        copy(self::$dbPath, $srcCopy);
        try {
            $sxgeo = new SxGeo('__sxgeo_test_fixture__.dat');
            self::assertSame('US', $sxgeo->getCountry('8.8.8.8'));
        } finally {
            @unlink($srcCopy);
        }
    }

    public function testConstructorThrowsOnTruncatedHeader(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sxgeo_');
        file_put_contents($tmp, 'SxG');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/signature/');
            new SxGeo($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function testConstructorThrowsOnZeroedHeader(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sxgeo_');
        // Valid signature + 37 zero bytes -> b_idx_len=0 => "wrong format".
        file_put_contents($tmp, 'SxG' . str_repeat("\0", 37));
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/format/');
            new SxGeo($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function testCountryOnlyBlockUsesParseCityCountryBranch(): void
    {
        $sxgeo = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY);
        // 1.17.0.1 is mapped to a country-only block in the bundled fixture.
        $city = $sxgeo->getCity('1.17.0.1');
        self::assertIsArray($city);
        self::assertArrayHasKey('city', $city);
        self::assertArrayHasKey('country', $city);
        // Country-only blocks expose lat/lon copied from the country record.
        self::assertArrayHasKey('lat', $city['city']);
        self::assertArrayHasKey('lon', $city['city']);

        $full = $sxgeo->getCityFull('1.17.0.1');
        self::assertIsArray($full);
        self::assertArrayHasKey('region', $full);
    }

    public function testGetCityOnPrivateIpReturnsFalse(): void
    {
        $sxgeo = new SxGeo(self::$dbPath);
        self::assertFalse($sxgeo->getCity('192.0.0.0'));
        self::assertFalse($sxgeo->getCityFull('not-an-ip'));
    }

    public function testGetReturnsCityArrayForCityDatabase(): void
    {
        $sxgeo = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY);
        $result = $sxgeo->get('8.8.8.8');
        self::assertIsArray($result);
        self::assertSame('US', $result['country']['iso']);
    }

    public function testGetFallsBackToCountryWhenMaxCityIsZero(): void
    {
        $sxgeo = new SxGeo(self::$dbPath);
        $this->forceCountryMode($sxgeo);

        // After forcing country mode the seek values are interpreted as
        // ISO indices; the call exercises the alternate branches in
        // get(), getCountry() and getCountryId() without crashing.
        self::assertIsString($sxgeo->get('8.8.8.8'));
        self::assertIsString($sxgeo->getCountry('8.8.8.8'));
        self::assertIsInt($sxgeo->getCountryId('8.8.8.8'));

        // Invalid IPs return the documented empty/zero values.
        self::assertSame('', $sxgeo->getCountry('not-an-ip'));
        self::assertSame(0, $sxgeo->getCountryId('not-an-ip'));
    }

    public function testParseCityReturnsFalseWhenPackIsEmpty(): void
    {
        $sxgeo = new SxGeo(self::$dbPath);
        $r = new ReflectionClass($sxgeo);

        $pack = $r->getProperty('pack');
        $pack->setValue($sxgeo, '');

        $parse = $r->getMethod('parseCity');
        self::assertFalse($parse->invoke($sxgeo, 1));
    }

    public function testAboutFallsBackToUnknownLabels(): void
    {
        $sxgeo = new SxGeo(self::$dbPath);
        $r = new ReflectionClass($sxgeo);
        $info = $r->getProperty('info');
        $value = $info->getValue($sxgeo);
        $value['charset'] = 99;
        $value['type']    = 99;
        $info->setValue($sxgeo, $value);

        $about = $sxgeo->about();
        self::assertSame('unknown', $about['Charset']);
        self::assertSame('unknown', $about['Type']);
    }

    public function testBatchModeSearchIdxAgreesWithFileMode(): void
    {
        // 4.4.4.4 has a wide block range that forces search_idx() to run.
        $file  = new SxGeo(self::$dbPath, SxGeo::MODE_FILE);
        $batch = new SxGeo(self::$dbPath, SxGeo::MODE_BATCH);
        $mem   = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY | SxGeo::MODE_BATCH);

        foreach (['4.4.4.4', '8.8.4.4', '208.67.220.220', '5.255.255.55'] as $ip) {
            $reference = $file->getCity($ip);
            self::assertEquals($reference, $batch->getCity($ip));
            self::assertEquals($reference, $mem->getCity($ip));
        }
    }

    public function testBoundaryIpsExerciseSearchClamps(): void
    {
        // The /8=4 and /8=8 blocks straddle index ranges so that the value
        // returned by search_idx falls outside [blocks.min, blocks.max] and
        // the clamping branches in get_num() are taken.
        $sxgeo = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY);
        foreach (['4.0.0.1', '4.255.255.255', '8.0.0.1', '8.255.255.254'] as $ip) {
            self::assertNotFalse($sxgeo->getCity($ip), $ip);
        }
    }

    public function testSingleEntryBlockExercisesSearchDbElseBranch(): void
    {
        // /8=11 has a single-entry block in the bundled fixture, which
        // takes the `$min++` branch of search_db().
        $sxgeo = new SxGeo(self::$dbPath, SxGeo::MODE_MEMORY);
        self::assertNotFalse($sxgeo->getCity('11.0.0.1'));

        $file = new SxGeo(self::$dbPath, SxGeo::MODE_FILE);
        self::assertNotFalse($file->getCity('11.0.0.1'));
    }

    #[DataProvider('unpackProvider')]
    public function testUnpackDecodesEverySupportedType(string $pack, string $item, array $expected): void
    {
        $sxgeo = new SxGeo(self::$dbPath);
        $r     = new ReflectionClass($sxgeo);
        $unpack = $r->getMethod('unpack');

        $actual = $unpack->invoke($sxgeo, $pack, $item);
        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $actual);
            if (is_float($value)) {
                self::assertEqualsWithDelta($value, $actual[$key], 1e-5, "Field {$key}");
            } else {
                self::assertSame($value, $actual[$key], "Field {$key}");
            }
        }
    }

    /**
     * @return iterable<string, array{0:string,1:string,2:array<string,mixed>}>
     */
    public static function unpackProvider(): iterable
    {
        // Empty input branch — every field collapses to its zero value.
        yield 'empty input' => [
            't:a/T:b/s:c/S:d/m:e/M:f/i:g/I:h/f:k/d:l/n2:m/N2:n/c3:o/b:p',
            '',
            ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0, 'e' => 0, 'f' => 0, 'g' => 0, 'h' => 0, 'k' => 0, 'l' => 0, 'm' => 0, 'n' => 0, 'o' => '', 'p' => ''],
        ];

        // Numeric scalar types.
        yield 't (int8 signed)' => ['t:v', "\xff", ['v' => -1]];
        yield 'T (int8 unsigned)' => ['T:v', "\xff", ['v' => 255]];
        yield 's (int16 signed)' => ['s:v', "\xff\xff", ['v' => -1]];
        yield 'S (int16 unsigned)' => ['S:v', "\x01\x00", ['v' => 1]];
        yield 'm (int24 signed negative)' => ['m:v', "\xff\xff\xff", ['v' => -1]];
        yield 'm (int24 signed positive)' => ['m:v', "\x01\x00\x00", ['v' => 1]];
        yield 'M (int24 unsigned)' => ['M:v', "\xff\xff\x7f", ['v' => 8388607]];
        yield 'i (int32 signed)' => ['i:v', "\xff\xff\xff\xff", ['v' => -1]];
        yield 'I (int32 unsigned)' => ['I:v', "\x01\x00\x00\x00", ['v' => 1]];
        yield 'f (float)' => ['f:v', pack('f', 1.5), ['v' => 1.5]];
        yield 'd (double)' => ['d:v', pack('d', 2.5), ['v' => 2.5]];

        // Decimal helpers (n/N): integer divided by 10^digits.
        yield 'n with 2 digits' => ['n2:v', "\xe8\x03", ['v' => 10.0]];   // 1000 / 100
        yield 'N with 5 digits' => ['N5:v', "\x40\x42\x0f\x00", ['v' => 10.0]]; // 1_000_000 / 1e5

        // String helpers.
        yield 'c (fixed length, trimmed)' => ['c4:v', 'ru  ', ['v' => 'ru']];
        yield 'b (null-terminated)' => ['b:v/T:after', "hello\0\xff", ['v' => 'hello', 'after' => 255]];
    }

    private function forceCountryMode(SxGeo $sxgeo): void
    {
        $r = new ReflectionClass($sxgeo);
        $prop = $r->getProperty('max_city');
        $prop->setValue($sxgeo, 0);
    }
}
