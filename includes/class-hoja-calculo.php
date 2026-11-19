<?php
/**
 * Lector de hojas de cálculo sin librerías externas.
 *
 * Reads the first sheet of a spreadsheet and returns its cells as rows:
 * - .xls  (Excel 97-2003, BIFF8 inside an OLE2 compound file: what Alexia exports)
 * - .xlsx (Office Open XML, with ZipArchive)
 * - .csv  (";" or "," separated, UTF-8 or Windows-1252)
 *
 * Only cell values are read (text and numbers); formats, formulas and images are ignored.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Hoja_Calculo {

    /**
     * @return array|WP_Error rows: array of arrays (0-based columns, '' for empty cells)
     */
    public static function leer($path, $nombre_original = '') {
        $ext = strtolower(pathinfo($nombre_original !== '' ? $nombre_original : $path, PATHINFO_EXTENSION));
        $head = (string) file_get_contents($path, false, null, 0, 8);

        try {
            if ($head === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                $rows = self::leer_xls($path);
            } elseif (substr($head, 0, 2) === 'PK') {
                $rows = self::leer_xlsx($path);
            } elseif ($ext === 'csv' || $ext === 'txt') {
                $rows = self::leer_csv($path);
            } else {
                return new WP_Error('hoja', __('Formato no reconocido. Sube el Excel tal como sale de Alexia (.xls), un .xlsx o un .csv.', 'area-profesores'));
            }
        } catch (Exception $e) {
            return new WP_Error('hoja', sprintf(__('No se pudo leer el fichero: %s', 'area-profesores'), $e->getMessage()));
        }

        return self::normalizar($rows);
    }

    /**
     * Same number of columns in every row, values as trimmed strings.
     */
    private static function normalizar($rows) {
        $max = 0;
        foreach ($rows as $r) {
            if ($r) {
                $max = max($max, max(array_keys($r)) + 1);
            }
        }
        $out = array();
        ksort($rows);
        $last = $rows ? max(array_keys($rows)) : -1;
        for ($i = 0; $i <= $last; $i++) {
            $row = array_fill(0, $max, '');
            if (isset($rows[$i])) {
                foreach ($rows[$i] as $c => $v) {
                    $row[$c] = trim(str_replace(array("\r\n", "\r"), "\n", (string) $v));
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * CSV
     * ------------------------------------------------------------------- */

    private static function leer_csv($path) {
        $data = (string) file_get_contents($path);
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            $data = substr($data, 3);
        } elseif (!preg_match('//u', $data)) {
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($data, "\n");
        $delim = substr_count((string) $first, ';') >= substr_count((string) $first, ',') ? ';' : ',';

        $rows = array();
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $data);
        rewind($fh);
        while (($r = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            $rows[] = $r === array(null) ? array() : $r;
        }
        fclose($fh);
        return $rows;
    }

    /* ---------------------------------------------------------------------
     * XLSX
     * ------------------------------------------------------------------- */

    private static function leer_xlsx($path) {
        if (!class_exists('ZipArchive')) {
            throw new Exception(__('el servidor no tiene ZipArchive para leer .xlsx; guarda el fichero como .xls o .csv', 'area-profesores'));
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception('xlsx');
        }

        $shared = array();
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $xml = self::xml($ss);
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } else {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }

        // First sheet of the workbook.
        $sheet_path = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $wbx = self::xml($wb);
            $relx = self::xml($rels);
            $first = $wbx->sheets->sheet[0];
            if ($first) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                foreach ($relx->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        $sheet_path = strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
                    }
                }
            }
        }

        $sheet = $zip->getFromName($sheet_path);
        $zip->close();
        if ($sheet === false) {
            throw new Exception('xlsx: hoja no encontrada');
        }

        $rows = array();
        $xml = self::xml($sheet);
        foreach ($xml->sheetData->row as $row) {
            $r = (int) $row['r'] - 1;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::columna(preg_replace('/\d+/', '', $ref));
                $t = (string) $c['t'];
                if ($t === 's') {
                    $v = isset($shared[(int) $c->v]) ? $shared[(int) $c->v] : '';
                } elseif ($t === 'inlineStr') {
                    $v = (string) $c->is->t;
                } else {
                    $v = (string) $c->v;
                }
                $rows[$r][$col] = $v;
            }
        }
        return $rows;
    }

    private static function xml($string) {
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($string, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            throw new Exception('XML');
        }
        return $xml;
    }

    private static function columna($letters) {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /* ---------------------------------------------------------------------
     * XLS (BIFF8 in an OLE2 compound document)
     * ------------------------------------------------------------------- */

    private static function u16($s, $o) {
        return unpack('v', substr($s, $o, 2))[1];
    }

    private static function u32($s, $o) {
        return unpack('V', substr($s, $o, 4))[1];
    }

    /**
     * Extract the "Workbook" stream from the OLE2 container.
     */
    private static function ole_workbook($data) {
        $sector_size = 1 << self::u16($data, 0x1E);
        $mini_size = 1 << self::u16($data, 0x20);
        $num_fat = self::u32($data, 0x2C);
        $first_dir = self::u32($data, 0x30);
        $mini_cutoff = self::u32($data, 0x38);
        $first_minifat = self::u32($data, 0x3C);
        $first_difat = self::u32($data, 0x44);
        $num_difat = self::u32($data, 0x48);

        $sector = function ($n) use ($data, $sector_size) {
            return substr($data, ($n + 1) * $sector_size, $sector_size);
        };

        // FAT sectors from the header DIFAT (109 entries) and the DIFAT chain.
        $fat_sectors = array();
        for ($i = 0; $i < 109 && count($fat_sectors) < $num_fat; $i++) {
            $fat_sectors[] = self::u32($data, 0x4C + $i * 4);
        }
        $d = $first_difat;
        for ($k = 0; $k < $num_difat && $d < 0xFFFFFFFA; $k++) {
            $sec = $sector($d);
            $per = $sector_size / 4 - 1;
            for ($i = 0; $i < $per && count($fat_sectors) < $num_fat; $i++) {
                $fat_sectors[] = self::u32($sec, $i * 4);
            }
            $d = self::u32($sec, $per * 4);
        }

        $fat = array();
        foreach ($fat_sectors as $fs) {
            $fat = array_merge($fat, array_values(unpack('V*', $sector($fs))));
        }

        $chain = function ($start, $table) {
            $out = array();
            $guard = 0;
            while ($start < 0xFFFFFFFA && isset($table[$start]) && $guard++ < 1000000) {
                $out[] = $start;
                $start = $table[$start];
            }
            return $out;
        };
        $read = function ($start, $size) use ($chain, $fat, $sector) {
            $buf = '';
            foreach ($chain($start, $fat) as $s) {
                $buf .= $sector($s);
            }
            return $size !== null ? substr($buf, 0, $size) : $buf;
        };

        // Directory entries.
        $dir = $read($first_dir, null);
        $entries = array();
        for ($o = 0; $o + 128 <= strlen($dir); $o += 128) {
            $len = self::u16($dir, $o + 0x40);
            if ($len < 2) {
                continue;
            }
            $name = mb_convert_encoding(substr($dir, $o, $len - 2), 'UTF-8', 'UTF-16LE');
            $entries[] = array(
                'name'  => $name,
                'type'  => ord($dir[$o + 0x42]),
                'start' => self::u32($dir, $o + 0x74),
                'size'  => self::u32($dir, $o + 0x78),
            );
        }

        $root = null;
        $book = null;
        foreach ($entries as $e) {
            if ($e['type'] === 5) {
                $root = $e;
            } elseif ($e['type'] === 2 && in_array($e['name'], array('Workbook', 'Book'), true)) {
                $book = $e;
            }
        }
        if (!$book) {
            throw new Exception(__('el fichero no es un libro de Excel', 'area-profesores'));
        }

        if ($book['size'] >= $mini_cutoff || !$root) {
            return $read($book['start'], $book['size']);
        }

        // Small stream: it lives in the mini stream.
        $minifat = array();
        foreach ($chain($first_minifat, $fat) as $s) {
            $minifat = array_merge($minifat, array_values(unpack('V*', $sector($s))));
        }
        $ministream = $read($root['start'], null);
        $buf = '';
        foreach ($chain($book['start'], $minifat) as $m) {
            $buf .= substr($ministream, $m * $mini_size, $mini_size);
        }
        return substr($buf, 0, $book['size']);
    }

    private static function leer_xls($path) {
        $wb = self::ole_workbook((string) file_get_contents($path));
        $len = strlen($wb);

        // 1) Workbook globals: shared strings and sheet offsets.
        $sst_chunks = array();
        $sheets = array();
        $pos = 0;
        $in_sst = false;
        while ($pos + 4 <= $len) {
            $type = self::u16($wb, $pos);
            $size = self::u16($wb, $pos + 2);
            $rec = substr($wb, $pos + 4, $size);
            $pos += 4 + $size;

            if ($type === 0x00FC) {
                $sst_chunks = array(substr($rec, 8));
                $in_sst = true;
                continue;
            }
            if ($type === 0x003C && $in_sst) {
                $sst_chunks[] = $rec;
                continue;
            }
            $in_sst = false;
            if ($type === 0x0085) {
                $sheets[] = self::u32($rec, 0);
            } elseif ($type === 0x000A) {
                break; // End of the globals.
            }
        }
        $sst = self::sst($sst_chunks);

        if (empty($sheets)) {
            throw new Exception('sin hojas');
        }

        // 2) First sheet.
        $rows = array();
        $pos = $sheets[0];
        $last_formula = null;
        while ($pos + 4 <= $len) {
            $type = self::u16($wb, $pos);
            $size = self::u16($wb, $pos + 2);
            $rec = substr($wb, $pos + 4, $size);
            $pos += 4 + $size;

            switch ($type) {
                case 0x000A: // EOF
                    break 2;
                case 0x00FD: // LABELSST
                    $rows[self::u16($rec, 0)][self::u16($rec, 2)] = isset($sst[self::u32($rec, 6)]) ? $sst[self::u32($rec, 6)] : '';
                    break;
                case 0x0204: // LABEL
                case 0x00D6: // RSTRING
                    $rows[self::u16($rec, 0)][self::u16($rec, 2)] = self::string_simple($rec, 6);
                    break;
                case 0x0203: // NUMBER
                    $rows[self::u16($rec, 0)][self::u16($rec, 2)] = self::numero(unpack('e', substr($rec, 6, 8))[1]);
                    break;
                case 0x027E: // RK
                    $rows[self::u16($rec, 0)][self::u16($rec, 2)] = self::numero(self::rk(self::u32($rec, 6)));
                    break;
                case 0x00BD: // MULRK
                    $r = self::u16($rec, 0);
                    $c = self::u16($rec, 2);
                    $n = (strlen($rec) - 6) / 6;
                    for ($i = 0; $i < $n; $i++) {
                        $rows[$r][$c + $i] = self::numero(self::rk(self::u32($rec, 4 + $i * 6 + 2)));
                    }
                    break;
                case 0x0006: // FORMULA: cached number (strings come in the next STRING record)
                    $r = self::u16($rec, 0);
                    $c = self::u16($rec, 2);
                    if (substr($rec, 12, 2) === "\xFF\xFF") {
                        $last_formula = array($r, $c);
                    } else {
                        $rows[$r][$c] = self::numero(unpack('e', substr($rec, 6, 8))[1]);
                    }
                    break;
                case 0x0207: // STRING (result of the previous formula)
                    if ($last_formula) {
                        $rows[$last_formula[0]][$last_formula[1]] = self::string_simple($rec, 0);
                        $last_formula = null;
                    }
                    break;
            }
        }

        return $rows;
    }

    private static function numero($f) {
        return (floor($f) == $f && abs($f) < 1e15) ? (string) (int) $f : (string) $f;
    }

    private static function rk($rk) {
        $div = $rk & 1;
        if ($rk & 2) {
            $v = $rk >> 2;
            if ($v & 0x20000000) {
                $v -= 0x40000000; // sign of the 30-bit integer
            }
        } else {
            $v = unpack('e', pack('VV', 0, $rk & 0xFFFFFFFC))[1];
        }
        return $div ? $v / 100 : $v;
    }

    /**
     * BIFF8 unicode string that does not cross records (LABEL, STRING).
     */
    private static function string_simple($rec, $o) {
        $cch = self::u16($rec, $o);
        $flags = ord($rec[$o + 2]);
        $p = $o + 3;
        if ($flags & 0x08) {
            $p += 2;
        }
        if ($flags & 0x04) {
            $p += 4;
        }
        return ($flags & 0x01)
            ? mb_convert_encoding(substr($rec, $p, $cch * 2), 'UTF-8', 'UTF-16LE')
            : mb_convert_encoding(substr($rec, $p, $cch), 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Shared string table, which may continue in CONTINUE records. When the
     * characters of a string are split, the next record starts with a new
     * option byte telling whether the rest is 8 or 16 bits per character.
     */
    private static function sst($chunks) {
        $out = array();
        if (empty($chunks)) {
            return $out;
        }
        $ci = 0;
        $p = 0;
        $data = $chunks[0];

        $next = function () use (&$ci, &$p, &$data, $chunks) {
            $ci++;
            if (!isset($chunks[$ci])) {
                return false;
            }
            $data = $chunks[$ci];
            $p = 0;
            return true;
        };
        $bytes = function ($n) use (&$p, &$data, $next) {
            $buf = '';
            while ($n > 0) {
                $avail = strlen($data) - $p;
                if ($avail <= 0) {
                    if (!$next()) {
                        break;
                    }
                    continue;
                }
                $take = min($n, $avail);
                $buf .= substr($data, $p, $take);
                $p += $take;
                $n -= $take;
            }
            return $buf;
        };

        while (true) {
            if ($p >= strlen($data) && !$next()) {
                break;
            }
            $hdr = $bytes(3);
            if (strlen($hdr) < 3) {
                break;
            }
            $cch = unpack('v', substr($hdr, 0, 2))[1];
            $flags = ord($hdr[2]);
            $runs = ($flags & 0x08) ? unpack('v', $bytes(2))[1] : 0;
            $ext = ($flags & 0x04) ? unpack('V', $bytes(4))[1] : 0;
            $wide = (bool) ($flags & 0x01);

            $text = '';
            $left = $cch;
            while ($left > 0) {
                $avail = strlen($data) - $p;
                if ($avail <= 0) {
                    if (!$next()) {
                        break;
                    }
                    $wide = (bool) (ord($data[0]) & 0x01);
                    $p = 1;
                    continue;
                }
                $chars = min($left, $wide ? intdiv($avail, 2) : $avail);
                if ($chars <= 0) {
                    $p = strlen($data);
                    continue;
                }
                $raw = substr($data, $p, $wide ? $chars * 2 : $chars);
                $p += strlen($raw);
                $text .= $wide ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE') : mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
                $left -= $chars;
            }
            $bytes($runs * 4 + $ext);
            $out[] = $text;
        }
        return $out;
    }
}
