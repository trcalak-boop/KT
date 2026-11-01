<?php
/* ==========================================================================
   ts-design-studio – export projektu jako ZIP bez závislostí (ZipArchive na hostingu nemusí být).
   TsStudioZip::build(['index.html' => '…', 'assets/fonts/a.woff2' => bajty]) → obsah .zip (deflate, CRC32, UTF-8 názvy)
   ========================================================================== */
final class TsStudioZip
{
    public static function build(array $files, int $time = 0): string
    {
        $time = $time ?: time(); $d = getdate($time);
        $dt = (($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2)) & 0xFFFF;
        $dd = ((($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday']) & 0xFFFF;
        $data = ''; $cd = ''; $n = 0;
        ksort($files);
        foreach ($files as $name => $raw) {
            $name = str_replace('\\', '/', (string) $name);
            if ($name === '' || str_starts_with($name, '/') || strpos($name, '..') !== false) throw new InvalidArgumentException('cesta v ZIP: ' . $name);
            $raw = (string) $raw; $crc = crc32($raw); $z = gzdeflate($raw, 6); $method = 8;
            if ($z === false || strlen($z) >= strlen($raw)) { $z = $raw; $method = 0; }
            $off = strlen($data);
            $hdr = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $dt, $dd, $crc, strlen($z), strlen($raw), strlen($name), 0);
            $data .= $hdr . $name . $z;
            $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $dt, $dd, $crc, strlen($z), strlen($raw), strlen($name), 0, 0, 0, 0, 0, $off) . $name;
            $n++;
        }
        return $data . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cd), strlen($data), 0);
    }
}
