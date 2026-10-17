<?php

namespace App\Services\Documents;

use Throwable;
use ZipArchive;

/**
 * XEM TRƯỚC TRỰC TUYẾN cho định dạng trình duyệt không tự mở được:
 *  - docx / odt / pptx: dựng lại các đoạn văn (đậm, tiêu đề) thành HTML đã escape;
 *  - xlsx: dựng bảng cho từng sheet (tối đa N dòng × M cột).
 * PDF, ảnh, video, âm thanh, văn bản thô trình duyệt tự hiển thị qua route preview.
 */
class FilePreview
{
    private const MAX_ROWS = 300;
    private const MAX_COLS = 40;

    /** @return array<int, array{style: string, text: string}> */
    public function documentParagraphs(string $path, string $extension): array
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                return [];
            }

            $xmls = [];
            if ($extension === 'pptx') {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $zip->getNameIndex($i), $m)) {
                        $xmls[(int) $m[1]] = $zip->getFromIndex($i);
                    }
                }
                ksort($xmls);
            } else {
                $xmls[] = $zip->getFromName($extension === 'odt' ? 'content.xml' : 'word/document.xml') ?: '';
            }
            $zip->close();

            $paragraphs = [];
            foreach ($xmls as $slideNo => $xml) {
                if ($extension === 'pptx') {
                    $paragraphs[] = ['style' => 'slide', 'text' => 'Trang chiếu ' . $slideNo];
                }

                preg_match_all('#<(w:p|a:p|text:p|text:h)[\s>].*?</\1>#s', $xml, $matches);
                foreach ($matches[0] as $p) {
                    $text = html_entity_decode(strip_tags(preg_replace('#<(w:tab|text:tab)[^>]*/>#', "\t", $p) ?? $p), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if (trim($text) === '') {
                        continue;
                    }

                    $style = 'p';
                    if (preg_match('#<w:pStyle w:val="(Heading|Title|Tieude|u)(\d?)#i', $p, $h) || str_starts_with($p, '<text:h')) {
                        $style = 'h';
                    } elseif (str_contains($p, '<w:b/>') || str_contains($p, '<w:b ')) {
                        $style = 'b';
                    }

                    $paragraphs[] = ['style' => $style, 'text' => $text];
                }
            }

            return $paragraphs;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int, array{name: string, rows: array<int, array<int, string>>, truncated: bool}> */
    public function spreadsheet(string $path): array
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                return [];
            }

            $shared = [];
            if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                preg_match_all('#<si>(.*?)</si>#s', $ss, $si);
                foreach ($si[1] as $item) {
                    $shared[] = html_entity_decode(strip_tags($item), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }

            // Tên sheet theo workbook.xml, đường dẫn theo workbook.xml.rels
            $names = [];
            $rels  = [];
            if (($wb = $zip->getFromName('xl/workbook.xml')) !== false) {
                preg_match_all('#<sheet [^>]*name="([^"]*)"[^>]*r:id="([^"]*)"#', $wb, $sh, PREG_SET_ORDER);
                foreach ($sh as $s) {
                    $names[$s[2]] = html_entity_decode($s[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            if (($wr = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
                preg_match_all('#<Relationship [^>]*Id="([^"]*)"[^>]*Target="([^"]*)"#', $wr, $rl, PREG_SET_ORDER);
                foreach ($rl as $r) {
                    $rels[$r[1]] = 'xl/' . ltrim(str_replace('/xl/', '', $r[2]), '/');
                }
            }

            $sheets = [];
            foreach ($names as $rid => $name) {
                $xml = isset($rels[$rid]) ? $zip->getFromName($rels[$rid]) : false;
                if ($xml === false) {
                    continue;
                }

                $rows = [];
                $truncated = false;
                preg_match_all('#<row[^>]*>(.*?)</row>#s', $xml, $rowMatches);

                foreach ($rowMatches[1] as $i => $rowXml) {
                    if ($i >= self::MAX_ROWS) {
                        $truncated = true;
                        break;
                    }

                    $cells = [];
                    preg_match_all('#<c r="([A-Z]+)\d+"([^>]*?)(?:/>|>(.*?)</c>)#s', $rowXml, $cellMatches, PREG_SET_ORDER);
                    foreach ($cellMatches as $c) {
                        $col = $this->columnIndex($c[1]);
                        if ($col >= self::MAX_COLS) {
                            continue;
                        }

                        $inner = $c[3] ?? '';
                        $value = '';
                        if (preg_match('#<v>(.*?)</v>#s', $inner, $v)) {
                            $value = str_contains($c[2], 't="s"') ? ($shared[(int) $v[1]] ?? '') : html_entity_decode($v[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                        } elseif (preg_match('#<is>(.*?)</is>#s', $inner, $is)) {
                            $value = html_entity_decode(strip_tags($is[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        }
                        $cells[$col] = $value;
                    }

                    if ($cells) {
                        $max   = max(array_keys($cells));
                        $rows[] = array_map(fn ($k) => $cells[$k] ?? '', range(0, $max));
                    }
                }

                $sheets[] = ['name' => $name, 'rows' => $rows, 'truncated' => $truncated];
            }
            $zip->close();

            return $sheets;
        } catch (Throwable) {
            return [];
        }
    }

    public function textContent(string $path, int $limit = 400000): string
    {
        $raw = (string) file_get_contents($path, false, null, 0, $limit);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return $raw;
    }

    private function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
