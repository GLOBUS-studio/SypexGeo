<?php

namespace GlobusStudio\SypexGeo\Tests;

use GlobusStudio\SypexGeo\SxGeo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests that exercise behaviour without requiring a real .dat database.
 */
final class SxGeoUnitTest extends TestCase
{
    public function testModeConstantsAreBitFlags(): void
    {
        self::assertSame(0, SxGeo::MODE_FILE);
        self::assertSame(1, SxGeo::MODE_MEMORY);
        self::assertSame(2, SxGeo::MODE_BATCH);
        self::assertSame(3, SxGeo::MODE_MEMORY | SxGeo::MODE_BATCH);
    }

    public function testIso2CountryTableHasExpectedAnchors(): void
    {
        // Reflect into a fresh class without invoking the constructor.
        $reflection = new \ReflectionClass(SxGeo::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $table      = $instance->id2iso;

        self::assertGreaterThanOrEqual(250, count($table));
        self::assertSame('', $table[0]);
        self::assertSame('DE', $table[56]);
        self::assertSame('PS', $table[178]);
        self::assertSame('TZ', $table[221]);
        self::assertSame('UM', $table[224]);
        self::assertContains('US', $table);
        self::assertContains('RU', $table);
        self::assertContains('UA', $table);

        // Every non-zero entry should be a valid two-letter ISO-style code.
        foreach ($table as $idx => $code) {
            if ($idx === 0) {
                continue;
            }
            self::assertMatchesRegularExpression(
                '/^[A-Z0-9]{2}$/',
                $code,
                "Invalid ISO code at index {$idx}: {$code}"
            );
        }
    }

    public function testConstructorThrowsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        new SxGeo(__DIR__ . '/fixtures/__definitely_missing__.dat');
    }

    public function testConstructorThrowsOnInvalidSignature(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sxgeo_');
        file_put_contents($tmp, str_repeat("\0", 200));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/signature|format/');
            new SxGeo($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
