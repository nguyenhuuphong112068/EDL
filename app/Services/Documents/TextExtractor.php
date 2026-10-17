<?php

namespace App\Services\Documents;

use App\Support\FileType;
use Throwable;
use ZipArchive;

/**
 * TRÍCH XUẤT NỘI DUNG để tìm kiếm toàn văn — không cần thư viện ngoài:
 *  - văn bản thô (txt, csv, md, json, xml, log…): đọc thẳng, tự đổi mã hoá về UTF-8;
 *  - Office Open XML (docx, xlsx, pptx) + OpenDocument (odt, ods, odp): giải nén zip, đọc XML;
 *  - PDF: giải nén các content stream (FlateDecode) và lấy chuỗi trong toán tử Tj/TJ.
 *    Đủ dùng cho PDF sinh từ Word/Excel; PDF scan (ảnh) không có chữ để trích.
 * Lỗi đọc file không làm hỏng việc tải lên — chỉ là tài liệu không có nội dung toàn văn.
 */
class TextExtractor
{
    public function extract(string $absolutePath, string $extension): ?string
    {
        $ext = FileType::normalize($extension);

        try {
            $text = match (true) {
                in_array($ext, ['txt', 'csv', 'md', 'json', 'xml', 'log', 'ini', 'cfg', 'hex', 'gsd', 'eds'], true) => $this->plain($absolutePath),
                in_array($ext, ['docx', 'docm'], true) => $this->zipXml($absolutePath, ['word/document.xml', 'word/header1.xml', 'word/footer1.xml']),
                in_array($ext, ['xlsx', 'xlsm'], true) => $this->zipXml($absolutePath, ['xl/sharedStrings.xml']),
                $ext === 'pptx'                        => $this->pptx($absolutePath),
                in_array($ext, ['odt', 'ods', 'odp'], true) => $this->zipXml($absolutePath, ['content.xml']),
                $ext === 'pdf'                         => $this->pdf($absolutePath),
                default                                => null,
            };
        } catch (Throwable) {
            return null;
        }

        if ($text === null) {
            return null;
        }

        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, config('edl.storage.max_index_chars'));
    }

    private function plain(string $path): string
    {
        $raw = (string) file_get_contents($path, false, null, 0, 5 * 1024 * 1024);

        return $this->toUtf8($raw);
    }

    /** @param array<int, string> $entries */
    private function zipXml(string $path, array $entries): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        $parts = [];
        foreach ($entries as $entry) {
            $xml = $zip->getFromName($entry);
            if ($xml !== false) {
                $parts[] = $this->xmlToText($xml);
            }
        }
        $zip->close();

        return $parts ? implode("\n", $parts) : null;
    }

    private function pptx(string $path): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        $slides = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $slides[(int) $m[1]] = $this->xmlToText($zip->getFromIndex($i));
            }
        }
        $zip->close();
        ksort($slides);

        return $slides ? implode("\n\n", $slides) : null;
    }

    /** XML Office → văn bản: xuống dòng ở cuối đoạn / ô / hàng, bỏ thẻ. */
    public function xmlToText(string $xml): string
    {
        $xml = preg_replace('#</(w:p|a:p|text:p|text:h|row|table:table-row)>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<(w:tab|w:br|text:tab|text:line-break)[^>]*/>#', ' ', $xml) ?? $xml;
        $xml = preg_replace('#</(w:tc|c|table:table-cell|si)>#', "\t", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function pdf(string $path): ?string
    {
        $data = (string) file_get_contents($path, false, null, 0, 30 * 1024 * 1024);

        if (! preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $data, $streams)) {
            return null;
        }

        $out = [];
        foreach ($streams[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate(substr($stream, 2));
            }
            if ($decoded === false) {
                $decoded = $stream;
            }

            if (! str_contains($decoded, 'Tj') && ! str_contains($decoded, 'TJ')) {
                continue;
            }

            // (chuỗi) Tj   và   [(chuỗi) -120 (chuỗi)] TJ
            if (preg_match_all('/\[(.*?)\]\s*TJ|\((.*?)(?<!\\\\)\)\s*Tj|(T\*|Td|TD|ET)/s', $decoded, $m, PREG_SET_ORDER)) {
                $line = '';
                foreach ($m as $op) {
                    if (! empty($op[3])) {
                        $out[] = $line;
                        $line  = '';
                    } elseif (! empty($op[1])) {
                        preg_match_all('/\((.*?)(?<!\\\\)\)/s', $op[1], $pieces);
                        $line .= implode('', array_map([$this, 'pdfString'], $pieces[1]));
                    } elseif (isset($op[2])) {
                        $line .= $this->pdfString($op[2]);
                    }
                }
                $out[] = $line;
            }
        }

        $text = implode("\n", array_filter(array_map('trim', $out)));

        return $text !== '' ? $this->toUtf8($text) : null;
    }

    private function pdfString(string $s): string
    {
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr(octdec($m[1])), $s) ?? $s;

        return strtr($s, ['\\(' => '(', '\\)' => ')', '\\\\' => '\\', '\\n' => "\n", '\\r' => '', '\\t' => ' ']);
    }

    private function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return preg_replace('/[^\P{C}\n\t]/u', '', $raw) ?? $raw;
    }
}
