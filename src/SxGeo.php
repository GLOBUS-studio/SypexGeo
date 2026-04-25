<?php

/*
 * This file is part of the globus-studio/sypexgeo package.
 *
 * (c) GLOBUS.studio
 * (c) Yevhen Leonidov
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GlobusStudio\SypexGeo;

use RuntimeException;

/**
 * Pure-PHP reader for the SypexGeo binary IP-to-geo database.
 *
 * Supports Country, City and City Max database variants in file, in-memory
 * and batch modes. Bit flags MODE_FILE / MODE_MEMORY / MODE_BATCH may be
 * combined; MEMORY_BATCH is the fastest combination at the cost of RAM.
 */
class SxGeo
{
    /** Read directly from the database file on every lookup. */
    public const MODE_FILE = 0;

    /** Load the whole database into memory. */
    public const MODE_MEMORY = 1;

    /** Pre-decode the byte/main indices for faster repeated lookups. */
    public const MODE_BATCH = 2;

    /** @var resource */
    protected $fh;

    protected string $ip1c = '';

    /** @var array<string, int> */
    protected array $info;

    protected int $range;
    protected int $db_begin;
    protected string $b_idx_str = '';
    protected string $m_idx_str = '';

    /** @var array<int, int> */
    protected array $b_idx_arr = [];
    protected int $b_idx_len;

    /** @var array<int, string> */
    protected array $m_idx_arr = [];
    protected int $m_idx_len;

    protected int $db_items;
    protected int $country_size;
    protected string $db = '';
    protected string $regions_db = '';
    protected string $cities_db = '';
    protected int $id_len;
    protected int $block_len;
    protected int $max_region;
    protected int $max_city;
    protected int $max_country;

    /** @var array<int, string>|string */
    protected $pack;

    protected bool $batch_mode = false;
    protected bool $memory_mode = false;

    /** @var array<int, string> ISO 3166-1 alpha-2 mapping by SypexGeo internal country id. */
    public array $id2iso = [
        '', 'AP', 'EU', 'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'CW', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU',
        'AW', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BM', 'BN', 'BO', 'BR', 'BS',
        'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
        'CO', 'CR', 'CU', 'CV', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG',
        'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'SX', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN',
        'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JM', 'JO', 'JP', 'KE',
        'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR',
        'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP',
        'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI',
        'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN',
        'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG',
        'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'ST', 'SV', 'SY', 'SZ', 'TC', 'TD', 'TF',
        'TG', 'TH', 'TJ', 'TK', 'TM', 'TN', 'TO', 'TL', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM',
        'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'RS', 'ZA',
        'ZM', 'ME', 'ZW', 'A1', 'XK', 'O1', 'AX', 'GG', 'IM', 'JE', 'BL', 'MF', 'BQ', 'SS',
    ];

    /**
     * @param string $db_file Absolute path to the database, or a filename relative to this file.
     * @param int    $type    Bit mask of MODE_* constants.
     *
     * @throws RuntimeException When the database cannot be opened or has an unexpected format.
     */
    public function __construct(string $db_file = 'SxGeo.dat', int $type = self::MODE_FILE)
    {
        $path = (is_file($db_file) || str_contains($db_file, DIRECTORY_SEPARATOR) || str_contains($db_file, '/'))
            ? $db_file
            : __DIR__ . DIRECTORY_SEPARATOR . $db_file;

        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException("Can't open SypexGeo database: {$db_file}");
        }
        $this->fh = $fh;

        // The v2.2 header is 40 bytes long (8 bytes wider than the original v2.x header).
        $header = fread($this->fh, 40);
        if ($header === false || strlen($header) < 40 || substr($header, 0, 3) !== 'SxG') {
            throw new RuntimeException("Wrong file signature: {$db_file}");
        }

        $info = unpack(
            'Cver/Ntime/Ctype/Ccharset/Cb_idx_len/nm_idx_len/nrange/Ndb_items/Cid_len/nmax_region/nmax_city/Nregion_size/Ncity_size/nmax_country/Ncountry_size/npack_size',
            substr($header, 3)
        );
        if ($info === false) { // @codeCoverageIgnoreStart
            throw new RuntimeException("Cannot parse header: {$db_file}");
        } // @codeCoverageIgnoreEnd
        if ($info['b_idx_len'] * $info['m_idx_len'] * $info['range'] * $info['db_items'] * $info['time'] * $info['id_len'] === 0) {
            throw new RuntimeException("Wrong file format: {$db_file}");
        }

        $this->range        = (int) $info['range'];
        $this->b_idx_len    = (int) $info['b_idx_len'];
        $this->m_idx_len    = (int) $info['m_idx_len'];
        $this->db_items     = (int) $info['db_items'];
        $this->id_len       = (int) $info['id_len'];
        $this->block_len    = 3 + $this->id_len;
        $this->max_region   = (int) $info['max_region'];
        $this->max_city     = (int) $info['max_city'];
        $this->max_country  = (int) $info['max_country'];
        $this->country_size = (int) $info['country_size'];
        $this->batch_mode   = (bool) ($type & self::MODE_BATCH);
        $this->memory_mode  = (bool) ($type & self::MODE_MEMORY);

        $this->pack       = $info['pack_size'] ? explode("\0", (string) fread($this->fh, $info['pack_size'])) : '';
        $this->b_idx_str  = (string) fread($this->fh, $info['b_idx_len'] * 4);
        $this->m_idx_str  = (string) fread($this->fh, $info['m_idx_len'] * 4);

        $this->db_begin = ftell($this->fh);

        if ($this->batch_mode) {
            $this->b_idx_arr = array_values(unpack('N*', $this->b_idx_str));
            unset($this->b_idx_str);
            $this->m_idx_arr = str_split($this->m_idx_str, 4);
            unset($this->m_idx_str);
        }
        if ($this->memory_mode) {
            $this->db         = (string) fread($this->fh, $this->db_items * $this->block_len);
            $this->regions_db = $info['region_size'] > 0 ? (string) fread($this->fh, $info['region_size']) : '';
            $this->cities_db  = $info['city_size']   > 0 ? (string) fread($this->fh, $info['city_size'])   : '';
        }

        $this->info = $info;
        $this->info['regions_begin'] = $this->db_begin + $this->db_items * $this->block_len;
        $this->info['cities_begin']  = $this->info['regions_begin'] + $info['region_size'];
    }

    public function __destruct()
    {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
    }

    protected function search_idx(string $ipn, int $min, int $max): int
    {
        if ($this->batch_mode) {
            while ($max - $min > 8) {
                $offset = ($min + $max) >> 1;
                if ($ipn > $this->m_idx_arr[$offset]) {
                    $min = $offset;
                } else {
                    $max = $offset;
                }
            }
            while ($ipn > $this->m_idx_arr[$min] && $min++ < $max) {
            }
        } else {
            while ($max - $min > 8) {
                $offset = ($min + $max) >> 1;
                if ($ipn > substr($this->m_idx_str, $offset * 4, 4)) {
                    $min = $offset;
                } else {
                    $max = $offset;
                }
            }
            while ($ipn > substr($this->m_idx_str, $min * 4, 4) && $min++ < $max) {
            }
        }

        return $min;
    }

    protected function search_db(string $str, string $ipn, int $min, int $max): int
    {
        if ($max - $min > 1) {
            $ipn = substr($ipn, 1);
            while ($max - $min > 8) {
                $offset = ($min + $max) >> 1;
                if ($ipn > substr($str, $offset * $this->block_len, 3)) {
                    $min = $offset;
                } else {
                    $max = $offset;
                }
            }
            while ($ipn >= substr($str, $min * $this->block_len, 3) && ++$min < $max) {
            }
        } else {
            $min++;
        }

        return hexdec(bin2hex(substr($str, $min * $this->block_len - $this->id_len, $this->id_len)));
    }

    /**
     * Resolve the raw seek/id for the given IPv4 address.
     *
     * @return int|false The internal seek/id, or false for invalid/private addresses.
     */
    public function get_num(string $ip)
    {
        $ip1n = (int) $ip;
        if ($ip1n === 0 || $ip1n === 10 || $ip1n === 127 || $ip1n >= $this->b_idx_len || false === ($ipn = ip2long($ip))) {
            return false;
        }
        $ipn = pack('N', $ipn);
        $this->ip1c = chr($ip1n);

        if ($this->batch_mode) {
            $blocks = ['min' => $this->b_idx_arr[$ip1n - 1], 'max' => $this->b_idx_arr[$ip1n]];
        } else {
            $blocks = unpack('Nmin/Nmax', substr($this->b_idx_str, ($ip1n - 1) * 4, 8));
        }

        if ($blocks['max'] - $blocks['min'] > $this->range) {
            $part = $this->search_idx($ipn, (int) floor($blocks['min'] / $this->range), (int) floor($blocks['max'] / $this->range) - 1);
            $min = $part > 0 ? $part * $this->range : 0;
            $max = $part > $this->m_idx_len ? $this->db_items : ($part + 1) * $this->range;
            if ($min < $blocks['min']) {
                $min = $blocks['min'];
            }
            if ($max > $blocks['max']) {
                $max = $blocks['max'];
            }
        } else {
            $min = $blocks['min'];
            $max = $blocks['max'];
        }

        $len = $max - $min;
        if ($this->memory_mode) {
            return $this->search_db($this->db, $ipn, $min, $max);
        }

        fseek($this->fh, $this->db_begin + $min * $this->block_len);

        return $this->search_db((string) fread($this->fh, $len * $this->block_len), $ipn, 0, $len);
    }

    /**
     * @return array<string, mixed>
     */
    protected function readData(int $seek, int $max, int $type): array
    {
        $raw = '';
        if ($seek && $max) {
            if ($this->memory_mode) {
                $raw = substr($type === 1 ? $this->regions_db : $this->cities_db, $seek, $max);
            } else {
                fseek($this->fh, $this->info[$type === 1 ? 'regions_begin' : 'cities_begin'] + $seek);
                $raw = (string) fread($this->fh, $max);
            }
        }

        return $this->unpack($this->pack[$type], $raw);
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function parseCity(int $seek, bool $full = false)
    {
        if (!$this->pack) {
            return false;
        }

        $only_country = false;
        if ($seek < $this->country_size) {
            $country = $this->readData($seek, $this->max_country, 0);
            $city = $this->unpack($this->pack[2]);
            $city['lat'] = $country['lat'];
            $city['lon'] = $country['lon'];
            $only_country = true;
        } else {
            $city = $this->readData($seek, $this->max_city, 2);
            $country = ['id' => $city['country_id'], 'iso' => $this->id2iso[$city['country_id']]];
            unset($city['country_id']);
        }

        if ($full) {
            $region = $this->readData($city['region_seek'], $this->max_region, 1);
            if (!$only_country) {
                $country = $this->readData($region['country_seek'], $this->max_country, 0);
            }
            unset($city['region_seek'], $region['country_seek']);

            return ['city' => $city, 'region' => $region, 'country' => $country];
        }

        unset($city['region_seek']);

        return ['city' => $city, 'country' => ['id' => $country['id'], 'iso' => $country['iso']]];
    }

    /**
     * Decode a SypexGeo-packed record according to the supplied pack format string.
     *
     * @return array<string, mixed>
     */
    protected function unpack(string $pack, string $item = ''): array
    {
        $unpacked = [];
        $empty = ($item === '');
        $pack = explode('/', $pack);
        $pos = 0;
        foreach ($pack as $p) {
            [$type, $name] = explode(':', $p);
            $type0 = $type[0];
            if ($empty) {
                $unpacked[$name] = $type0 === 'b' || $type0 === 'c' ? '' : 0;
                continue;
            }
            switch ($type0) {
                case 't':
                case 'T':
                    $l = 1;
                    break;
                case 's':
                case 'n':
                case 'S':
                    $l = 2;
                    break;
                case 'm':
                case 'M':
                    $l = 3;
                    break;
                case 'd':
                    $l = 8;
                    break;
                case 'c':
                    $l = (int) substr($type, 1);
                    break;
                case 'b':
                    $l = strpos($item, "\0", $pos) - $pos;
                    break;
                default:
                    $l = 4;
            }
            $val = substr($item, $pos, $l);
            $v = 0;
            switch ($type0) {
                case 't':
                    $v = unpack('c', $val);
                    break;
                case 'T':
                    $v = unpack('C', $val);
                    break;
                case 's':
                    $v = unpack('s', $val);
                    break;
                case 'S':
                    $v = unpack('S', $val);
                    break;
                case 'm':
                    $v = unpack('l', $val . (ord($val[2]) >> 7 ? "\xff" : "\0"));
                    break;
                case 'M':
                    $v = unpack('L', $val . "\0");
                    break;
                case 'i':
                    $v = unpack('l', $val);
                    break;
                case 'I':
                    $v = unpack('L', $val);
                    break;
                case 'f':
                    $v = unpack('f', $val);
                    break;
                case 'd':
                    $v = unpack('d', $val);
                    break;
                case 'n':
                    $v = current(unpack('s', $val)) / pow(10, (int) $type[1]);
                    break;
                case 'N':
                    $v = current(unpack('l', $val)) / pow(10, (int) $type[1]);
                    break;
                case 'c':
                    $v = rtrim($val, ' ');
                    break;
                case 'b':
                    $v = $val;
                    $l++;
                    break;
            }
            $pos += $l;
            $unpacked[$name] = is_array($v) ? current($v) : $v;
        }

        return $unpacked;
    }

    /**
     * Auto-pick: country code for a Country DB, full city structure for a City DB.
     *
     * @return string|array<string, mixed>|false
     */
    public function get(string $ip)
    {
        return $this->max_city ? $this->getCity($ip) : $this->getCountry($ip);
    }

    /**
     * @return string ISO 3166-1 alpha-2 country code; empty string for unknown/invalid input.
     */
    public function getCountry(string $ip): string
    {
        if ($this->max_city) {
            $tmp = $this->parseCity((int) $this->get_num($ip));

            return $tmp ? (string) $tmp['country']['iso'] : '';
        }

        $id = $this->get_num($ip);

        return $id === false ? '' : ($this->id2iso[$id] ?? '');
    }

    public function getCountryId(string $ip): int
    {
        if ($this->max_city) {
            $tmp = $this->parseCity((int) $this->get_num($ip));

            return $tmp ? (int) $tmp['country']['id'] : 0;
        }

        $id = $this->get_num($ip);

        return $id === false ? 0 : (int) $id;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getCity(string $ip)
    {
        $seek = $this->get_num($ip);

        return $seek ? $this->parseCity((int) $seek) : false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getCityFull(string $ip)
    {
        $seek = $this->get_num($ip);

        return $seek ? $this->parseCity((int) $seek, true) : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function about(): array
    {
        $charset = ['utf-8', 'latin1', 'cp1251'];
        $types = ['n/a', 'SxGeo Country', 'SxGeo City RU', 'SxGeo City EN', 'SxGeo City', 'SxGeo City Max RU', 'SxGeo City Max EN', 'SxGeo City Max'];

        return [
            'Created'              => date('Y.m.d', $this->info['time']),
            'Timestamp'            => $this->info['time'],
            'Charset'              => $charset[$this->info['charset']] ?? 'unknown',
            'Type'                 => $types[$this->info['type']] ?? 'unknown',
            'Byte Index'           => $this->b_idx_len,
            'Main Index'           => $this->m_idx_len,
            'Blocks In Index Item' => $this->range,
            'IP Blocks'            => $this->db_items,
            'Block Size'           => $this->block_len,
            'City' => [
                'Max Length' => $this->max_city,
                'Total Size' => $this->info['city_size'],
            ],
            'Region' => [
                'Max Length' => $this->max_region,
                'Total Size' => $this->info['region_size'],
            ],
            'Country' => [
                'Max Length' => $this->max_country,
                'Total Size' => $this->info['country_size'],
            ],
        ];
    }
}
