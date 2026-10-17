<?php

namespace App\Support;

/**
 * MÃ QR
 *
 * Vẽ mã QR thành ảnh SVG ngay trong trang in, không cần thư viện ngoài - máy in nào in
 * được trang web là in được nhãn. Song song với App\Support\Barcode128 (mã vạch 1 chiều)
 * nhưng cho nhãn lô vật tư dùng mã QR để quét nhanh bằng điện thoại.
 *
 * Cài đặt theo thuật toán QR Code model 2 (ISO/IEC 18004): chỉ hỗ trợ chế độ byte
 * (đủ cho mã lô toàn chữ + số), tự chọn phiên bản 1-40 vừa đủ chứa dữ liệu, tự chọn
 * mặt nạ tốt nhất theo điểm phạt chuẩn. Tham chiếu: bản dựng "QR Code generator library"
 * của Nayuki (MIT) - đã port sang PHP thuần.
 */
class QrCode
{
    /** Số codeword sửa lỗi cho mỗi block, theo [mức ECC (L,M,Q,H)][phiên bản 1..40]. */
    private const ECC_CODEWORDS_PER_BLOCK = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Số block sửa lỗi, theo [mức ECC][phiên bản]. */
    private const NUM_ERROR_CORRECTION_BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    /** Mức ECC -> [thứ tự bảng, 4 bit thông tin định dạng]. */
    private const ECC_LEVELS = [
        'L' => [0, 1],
        'M' => [1, 0],
        'Q' => [2, 3],
        'H' => [3, 2],
    ];

    private const PENALTY_N1 = 3;
    private const PENALTY_N2 = 3;
    private const PENALTY_N3 = 40;
    private const PENALTY_N4 = 10;

    private int $size;

    /** @var array<int, array<int, bool>> */
    private array $modules = [];

    /** @var array<int, array<int, bool>> */
    private array $isFunction = [];

    /**
     * Vẽ mã QR của $data thành thẻ <svg> tự co giãn theo ô chứa.
     *
     * @param  string  $data     Nội dung mã QR (mã hoá UTF-8)
     * @param  string  $ecc      Mức sửa lỗi: L | M | Q | H
     * @param  int     $border   Vùng trắng quanh mã, tính theo module (tối thiểu 4)
     * @return string  Thẻ SVG, hoặc chuỗi rỗng nếu không mã hoá được
     */
    public static function svg(string $data, string $ecc = 'M', int $border = 4): string
    {
        try {
            $qr = new self($data, $ecc);
        } catch (\Throwable $e) {
            return '';
        }

        return $qr->toSvg(max(0, $border));
    }

    /**
     * Như svg() nhưng trả kèm số module (cạnh mã, CHƯA tính viền) để nơi gọi tự
     * tính bề rộng in sao cho phần mã đạt đúng số mm mong muốn.
     *
     * @param  string  $data    Nội dung mã QR
     * @param  string  $ecc     Mức sửa lỗi: L | M | Q | H
     * @param  int     $border  Vùng trắng quanh mã, tính theo module
     * @return array{svg: string, modules: int, border: int}
     */
    public static function render(string $data, string $ecc = 'M', int $border = 4): array
    {
        $border = max(0, $border);

        try {
            $qr = new self($data, $ecc);
        } catch (\Throwable $e) {
            return ['svg' => '', 'modules' => 0, 'border' => $border];
        }

        return ['svg' => $qr->toSvg($border), 'modules' => $qr->size, 'border' => $border];
    }

    private function __construct(string $data, string $ecc)
    {
        $ecc = strtoupper($ecc);
        if (! isset(self::ECC_LEVELS[$ecc])) {
            $ecc = 'M';
        }
        [$eclOrdinal, $eclFormatBits] = self::ECC_LEVELS[$ecc];

        $dataBytes = array_values(unpack('C*', $data === '' ? "\0" : $data));
        if ($data === '') {
            $dataBytes = [];
        }
        $numBytes = count($dataBytes);

        // Chọn phiên bản nhỏ nhất vừa đủ chứa dữ liệu ở mức ECC đã cho
        $version = 0;
        for ($v = 1; $v <= 40; $v++) {
            $capacityBits = $this->numDataCodewords($v, $eclOrdinal) * 8;
            $usedBits = 4 + $this->byteModeCountBits($v) + 8 * $numBytes;
            if ($usedBits <= $capacityBits) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new \RuntimeException('Dữ liệu quá dài cho mã QR.');
        }

        $this->size = $version * 4 + 17;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = array_fill(0, $this->size, array_fill(0, $this->size, false));

        // ----- Chuỗi bit dữ liệu -----
        $bb = [];
        $append = function (int $val, int $len) use (&$bb): void {
            for ($i = $len - 1; $i >= 0; $i--) {
                $bb[] = ($val >> $i) & 1;
            }
        };

        $append(0x4, 4); // chế độ byte
        $append($numBytes, $this->byteModeCountBits($version));
        foreach ($dataBytes as $b) {
            $append($b, 8);
        }

        $capacityBits = $this->numDataCodewords($version, $eclOrdinal) * 8;
        $append(0, min(4, $capacityBits - count($bb)));
        $append(0, (8 - count($bb) % 8) % 8);
        for ($pad = 0xEC; count($bb) < $capacityBits; $pad ^= 0xEC ^ 0x11) {
            $append($pad, 8);
        }

        $dataCodewords = array_fill(0, intdiv($capacityBits, 8), 0);
        foreach ($bb as $i => $bit) {
            $dataCodewords[$i >> 3] |= $bit << (7 - ($i & 7));
        }

        $allCodewords = $this->addEccAndInterleave($dataCodewords, $version, $eclOrdinal);

        // ----- Vẽ -----
        $this->drawFunctionPatterns($version, $eclFormatBits);
        $this->drawCodewords($allCodewords);

        // Chọn mặt nạ tốt nhất theo điểm phạt
        $bestMask = 0;
        $minPenalty = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->applyMask($m);
            $this->drawFormatBits($eclFormatBits, $m);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $minPenalty = $penalty;
                $bestMask = $m;
            }
            $this->applyMask($m); // hoàn tác (XOR)
        }
        $this->applyMask($bestMask);
        $this->drawFormatBits($eclFormatBits, $bestMask);
    }

    /* ==========================================================
     |  BẢNG SỐ LIỆU
     ========================================================== */

    private function numRawDataModules(int $ver): int
    {
        $result = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $numAlign = intdiv($ver, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($ver >= 7) {
                $result -= 36;
            }
        }

        return $result;
    }

    private function numDataCodewords(int $ver, int $ecl): int
    {
        return intdiv($this->numRawDataModules($ver), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$ecl][$ver] * self::NUM_ERROR_CORRECTION_BLOCKS[$ecl][$ver];
    }

    private function byteModeCountBits(int $ver): int
    {
        return [8, 16, 16][intdiv($ver + 7, 17)];
    }

    /* ==========================================================
     |  SỬA LỖI REED-SOLOMON
     ========================================================== */

    /** @param array<int,int> $data @return array<int,int> */
    private function addEccAndInterleave(array $data, int $ver, int $ecl): array
    {
        $numBlocks = self::NUM_ERROR_CORRECTION_BLOCKS[$ecl][$ver];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$ecl][$ver];
        $rawCodewords = intdiv($this->numRawDataModules($ver), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $blocks = [];
        $rsDiv = $this->rsComputeDivisor($blockEccLen);
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $ecc = $this->rsComputeRemainder($dat, $rsDiv);
            if ($i < $numShortBlocks) {
                $dat[] = 0;
            }
            $blocks[] = array_merge($dat, $ecc);
        }

        $result = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            for ($j = 0; $j < $numBlocks; $j++) {
                // Bỏ byte đệm ở block ngắn
                if ($i != $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $blocks[$j][$i];
                }
            }
        }

        return $result;
    }

    /** @return array<int,int> */
    private function rsComputeDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;

        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = $this->gfMul($root, 0x02);
        }

        return $result;
    }

    /** @param array<int,int> $data @param array<int,int> $divisor @return array<int,int> */
    private function rsComputeRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $d) {
                $result[$i] ^= $this->gfMul($d, $factor);
            }
        }

        return $result;
    }

    private function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    /* ==========================================================
     |  HOẠ TIẾT CHỨC NĂNG
     ========================================================== */

    private function drawFunctionPatterns(int $version, int $eclFormatBits): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 == 0);
            $this->setFunctionModule($i, 6, $i % 2 == 0);
        }

        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        $alignPos = $this->alignmentPatternPositions($version);
        $numAlign = count($alignPos);
        for ($i = 0; $i < $numAlign; $i++) {
            for ($j = 0; $j < $numAlign; $j++) {
                if (! (($i == 0 && $j == 0) || ($i == 0 && $j == $numAlign - 1) || ($i == $numAlign - 1 && $j == 0))) {
                    $this->drawAlignmentPattern($alignPos[$i], $alignPos[$j]);
                }
            }
        }

        $this->drawFormatBits($eclFormatBits, 0);
        $this->drawVersionInfo($version);
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunctionModule($xx, $yy, $dist != 2 && $dist != 4);
                }
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) != 1);
            }
        }
    }

    /** @return array<int,int> */
    private function alignmentPatternPositions(int $ver): array
    {
        if ($ver == 1) {
            return [];
        }
        $numAlign = intdiv($ver, 7) + 2;
        $step = ($ver == 32) ? 26 : (int) (ceil(($ver * 4 + 4) / ($numAlign * 2 - 2)) * 2);

        $result = [6];
        for ($pos = $this->size - 7; count($result) < $numAlign; $pos -= $step) {
            array_splice($result, 1, 0, [$pos]);
        }

        return $result;
    }

    private function drawFormatBits(int $eclFormatBits, int $mask): void
    {
        $data = $eclFormatBits << 3 | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, $this->getBit($bits, $i));
        }
        $this->setFunctionModule(8, 7, $this->getBit($bits, 6));
        $this->setFunctionModule(8, 8, $this->getBit($bits, 7));
        $this->setFunctionModule(7, 8, $this->getBit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, $this->getBit($bits, $i));
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, $this->getBit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, $this->getBit($bits, $i));
        }
        $this->setFunctionModule(8, $this->size - 8, true);
    }

    private function drawVersionInfo(int $ver): void
    {
        if ($ver < 7) {
            return;
        }

        $rem = $ver;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = $ver << 12 | $rem;

        for ($i = 0; $i < 18; $i++) {
            $color = $this->getBit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $color);
            $this->setFunctionModule($b, $a, $color);
        }
    }

    private function setFunctionModule(int $x, int $y, bool $isDark): void
    {
        $this->modules[$y][$x] = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    /* ==========================================================
     |  DỮ LIỆU + MẶT NẠ
     ========================================================== */

    /** @param array<int,int> $data */
    private function drawCodewords(array $data): void
    {
        $i = 0;
        $dataLenBits = count($data) * 8;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right == 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) == 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (! $this->isFunction[$y][$x] && $i < $dataLenBits) {
                        $this->modules[$y][$x] = $this->getBit($data[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                switch ($mask) {
                    case 0: $invert = ($x + $y) % 2 == 0; break;
                    case 1: $invert = $y % 2 == 0; break;
                    case 2: $invert = $x % 3 == 0; break;
                    case 3: $invert = ($x + $y) % 3 == 0; break;
                    case 4: $invert = (intdiv($x, 3) + intdiv($y, 2)) % 2 == 0; break;
                    case 5: $invert = ($x * $y) % 2 + ($x * $y) % 3 == 0; break;
                    case 6: $invert = (($x * $y) % 2 + ($x * $y) % 3) % 2 == 0; break;
                    case 7: $invert = (($x + $y) % 2 + ($x * $y) % 3) % 2 == 0; break;
                    default: $invert = false;
                }
                if ($invert) {
                    $this->modules[$y][$x] = ! $this->modules[$y][$x];
                }
            }
        }
    }

    private function penaltyScore(): int
    {
        $result = 0;
        $size = $this->size;

        // Quy tắc 1 + 3: dãy cùng màu trên hàng
        for ($y = 0; $y < $size; $y++) {
            $runColor = false;
            $runX = 0;
            $runHistory = array_fill(0, 7, 0);
            for ($x = 0; $x < $size; $x++) {
                if ($this->modules[$y][$x] == $runColor) {
                    $runX++;
                    if ($runX == 5) {
                        $result += self::PENALTY_N1;
                    } elseif ($runX > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runX, $runHistory);
                    if (! $runColor) {
                        $result += $this->finderPenaltyCountPatterns($runHistory) * self::PENALTY_N3;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runX = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runX, $runHistory) * self::PENALTY_N3;
        }

        // Quy tắc 1 + 3: dãy cùng màu trên cột
        for ($x = 0; $x < $size; $x++) {
            $runColor = false;
            $runY = 0;
            $runHistory = array_fill(0, 7, 0);
            for ($y = 0; $y < $size; $y++) {
                if ($this->modules[$y][$x] == $runColor) {
                    $runY++;
                    if ($runY == 5) {
                        $result += self::PENALTY_N1;
                    } elseif ($runY > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runY, $runHistory);
                    if (! $runColor) {
                        $result += $this->finderPenaltyCountPatterns($runHistory) * self::PENALTY_N3;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runY = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runY, $runHistory) * self::PENALTY_N3;
        }

        // Quy tắc 2: khối 2x2 cùng màu
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $color = $this->modules[$y][$x];
                if ($color == $this->modules[$y][$x + 1]
                    && $color == $this->modules[$y + 1][$x]
                    && $color == $this->modules[$y + 1][$x + 1]) {
                    $result += self::PENALTY_N2;
                }
            }
        }

        // Quy tắc 4: cân bằng module tối / sáng
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $cell) {
                if ($cell) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        for ($k = 0; $dark * 20 < (9 - $k) * $total || $dark * 20 > (11 + $k) * $total; $k++) {
            $result += self::PENALTY_N4;
        }

        return $result;
    }

    /** @param array<int,int> $runHistory */
    private function finderPenaltyCountPatterns(array $runHistory): int
    {
        $n = $runHistory[1];
        $core = $n > 0 && $runHistory[2] == $n && $runHistory[3] == $n * 3 && $runHistory[4] == $n && $runHistory[5] == $n;

        return ($core && $runHistory[0] >= $n * 4 && $runHistory[6] >= $n ? 1 : 0)
            + ($core && $runHistory[6] >= $n * 4 && $runHistory[0] >= $n ? 1 : 0);
    }

    /** @param array<int,int> $runHistory */
    private function finderPenaltyTerminateAndCount(bool $currentRunColor, int $currentRunLength, array &$runHistory): int
    {
        if ($currentRunColor) {
            $this->finderPenaltyAddHistory($currentRunLength, $runHistory);
            $currentRunLength = 0;
        }
        $currentRunLength += $this->size;
        $this->finderPenaltyAddHistory($currentRunLength, $runHistory);

        return $this->finderPenaltyCountPatterns($runHistory);
    }

    /** @param array<int,int> $runHistory */
    private function finderPenaltyAddHistory(int $currentRunLength, array &$runHistory): void
    {
        if ($runHistory[0] == 0) {
            $currentRunLength += $this->size;
        }
        array_pop($runHistory);
        array_unshift($runHistory, $currentRunLength);
    }

    private function getBit(int $x, int $i): bool
    {
        return (($x >> $i) & 1) != 0;
    }

    /* ==========================================================
     |  XUẤT SVG
     ========================================================== */

    private function toSvg(int $border): string
    {
        $dim = $this->size + $border * 2;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$dim.' '.$dim.'" '
            .'preserveAspectRatio="xMidYMid meet" shape-rendering="crispEdges">';
        $svg .= '<rect width="'.$dim.'" height="'.$dim.'" fill="#fff"/>';

        // Gộp các module tối liền nhau trên một hàng thành một hình chữ nhật
        for ($y = 0; $y < $this->size; $y++) {
            $x = 0;
            while ($x < $this->size) {
                if (! $this->modules[$y][$x]) {
                    $x++;
                    continue;
                }
                $run = 1;
                while ($x + $run < $this->size && $this->modules[$y][$x + $run]) {
                    $run++;
                }
                $svg .= '<rect x="'.($x + $border).'" y="'.($y + $border).'" width="'.$run.'" height="1" fill="#000"/>';
                $x += $run;
            }
        }

        return $svg.'</svg>';
    }
}
