<?php

namespace App\Services;

/**
 * Self-contained Pure PHP QR Code SVG Generator.
 * Based on Kazuhiko Arase's QR Code implementation (MIT License).
 * Supports standard Model 2 QR codes with Error Correction.
 * Generates crisp vector SVG without any external dependencies (no GD or Imagick needed).
 */
class QrCodeService
{
    /**
     * Generate an SVG QR code string.
     */
    public static function svg(string $text, int $size = 200, int $margin = 4, string $darkColor = '#000000', string $lightColor = '#ffffff'): string
    {
        $qr = self::createQr($text);
        $moduleCount = $qr->getModuleCount();
        $totalModules = $moduleCount + ($margin * 2);

        $pathData = '';
        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                if ($qr->isDark($row, $col)) {
                    $x = $col + $margin;
                    $y = $row + $margin;
                    $pathData .= "M{$x},{$y}h1v1h-1z ";
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "
";
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 ' . $totalModules . ' ' . $totalModules . '" width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges">';
        if ($lightColor !== 'transparent' && $lightColor !== '') {
            $svg .= '<rect width="100%" height="100%" fill="' . htmlspecialchars($lightColor, ENT_QUOTES, 'UTF-8') . '"/>';
        }
        $svg .= '<path d="' . trim($pathData) . '" fill="' . htmlspecialchars($darkColor, ENT_QUOTES, 'UTF-8') . '"/>';
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Generate a data:image/svg+xml;base64,... URI.
     */
    public static function dataUri(string $text, int $size = 200, int $margin = 4): string
    {
        $svg = self::svg($text, $size, $margin);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private static function createQr(string $text): QrCodeModel
    {
        // Try versions 1 to 10
        for ($typeNumber = 1; $typeNumber <= 10; $typeNumber++) {
            try {
                $qr = new QrCodeModel($typeNumber, QrCodeModel::ERROR_CORRECT_M);
                $qr->addData($text);
                $qr->make();
                return $qr;
            } catch (\Exception $e) {
                // If text is too long for this version, try next version
                if ($typeNumber === 10) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Failed to encode QR code data.');
    }
}

class QrCodeModel
{
    public const ERROR_CORRECT_L = 1;
    public const ERROR_CORRECT_M = 0;
    public const ERROR_CORRECT_Q = 3;
    public const ERROR_CORRECT_H = 2;

    private int $typeNumber;
    private int $errorCorrectLevel;
    /** @var array<int, array<int, bool|null>> */
    private array $modules = [];
    private int $moduleCount = 0;
    /** @var array<int, int> */
    private array $dataList = [];

    public function __construct(int $typeNumber, int $errorCorrectLevel)
    {
        $this->typeNumber = $typeNumber;
        $this->errorCorrectLevel = $errorCorrectLevel;
    }

    public function addData(string $data): void
    {
        $bytes = unpack('C*', $data);
        if ($bytes !== false) {
            foreach ($bytes as $b) {
                $this->dataList[] = $b;
            }
        }
    }

    public function isDark(int $row, int $col): bool
    {
        return !empty($this->modules[$row][$col]);
    }

    public function getModuleCount(): int
    {
        return $this->moduleCount;
    }

    public function make(): void
    {
        $this->makeImpl(false, $this->getBestMaskPattern());
    }

    private function getBestMaskPattern(): int
    {
        $minLostPoint = PHP_INT_MAX;
        $pattern = 0;

        for ($i = 0; $i < 8; $i++) {
            $this->makeImpl(true, $i);
            $lostPoint = QrUtil::getLostPoint($this);
            if ($lostPoint < $minLostPoint) {
                $minLostPoint = $lostPoint;
                $pattern = $i;
            }
        }

        return $pattern;
    }

    private function makeImpl(bool $test, int $maskPattern): void
    {
        $this->moduleCount = $this->typeNumber * 4 + 17;
        $this->modules = [];
        for ($r = 0; $r < $this->moduleCount; $r++) {
            $this->modules[$r] = array_fill(0, $this->moduleCount, null);
        }

        $this->setupPositionProbePattern(0, 0);
        $this->setupPositionProbePattern($this->moduleCount - 7, 0);
        $this->setupPositionProbePattern(0, $this->moduleCount - 7);
        $this->setupPositionAdjustPattern();
        $this->setupTimingPattern();
        $this->setupTypeInfo($test, $maskPattern);

        if ($this->typeNumber >= 7) {
            $this->setupTypeNumber($test);
        }

        $data = self::createData($this->typeNumber, $this->errorCorrectLevel, $this->dataList);
        $this->mapData($data, $maskPattern);
    }

    private function setupPositionProbePattern(int $row, int $col): void
    {
        for ($r = -1; $r <= 7; $r++) {
            if ($row + $r <= -1 || $this->moduleCount <= $row + $r) continue;
            for ($c = -1; $c <= 7; $c++) {
                if ($col + $c <= -1 || $this->moduleCount <= $col + $c) continue;
                if ((0 <= $r && $r <= 6 && ($c === 0 || $c === 6))
                    || (0 <= $c && $c <= 6 && ($r === 0 || $r === 6))
                    || (2 <= $r && $r <= 4 && 2 <= $c && $c <= 4)) {
                    $this->modules[$row + $r][$col + $c] = true;
                } else {
                    $this->modules[$row + $r][$col + $c] = false;
                }
            }
        }
    }

    private function setupTimingPattern(): void
    {
        for ($r = 8; $r < $this->moduleCount - 8; $r++) {
            if ($this->modules[$r][6] !== null) continue;
            $this->modules[$r][6] = ($r % 2 === 0);
        }
        for ($c = 8; $c < $this->moduleCount - 8; $c++) {
            if ($this->modules[6][$c] !== null) continue;
            $this->modules[6][$c] = ($c % 2 === 0);
        }
    }

    private function setupPositionAdjustPattern(): void
    {
        $pos = QrUtil::getPatternPosition($this->typeNumber);
        $len = count($pos);
        for ($i = 0; $i < $len; $i++) {
            for ($j = 0; $j < $len; $j++) {
                $row = $pos[$i];
                $col = $pos[$j];
                if ($this->modules[$row][$col] !== null) continue;

                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        if ($r === -2 || $r === 2 || $c === -2 || $c === 2 || ($r === 0 && $c === 0)) {
                            $this->modules[$row + $r][$col + $c] = true;
                        } else {
                            $this->modules[$row + $r][$col + $c] = false;
                        }
                    }
                }
            }
        }
    }

    private function setupTypeNumber(bool $test): void
    {
        $bits = QrUtil::getBCHTypeNumber($this->typeNumber);
        for ($i = 0; $i < 18; $i++) {
            $mod = (! $test && (($bits >> $i) & 1) === 1);
            $this->modules[(int) floor($i / 3)][$i % 3 + $this->moduleCount - 8 - 3] = $mod;
            $this->modules[$i % 3 + $this->moduleCount - 8 - 3][(int) floor($i / 3)] = $mod;
        }
    }

    private function setupTypeInfo(bool $test, int $maskPattern): void
    {
        $data = ($this->errorCorrectLevel << 3) | $maskPattern;
        $bits = QrUtil::getBCHTypeInfo($data);

        for ($i = 0; $i < 15; $i++) {
            $mod = (! $test && (($bits >> $i) & 1) === 1);
            if ($i < 6) {
                $this->modules[$i][8] = $mod;
            } elseif ($i < 8) {
                $this->modules[$i + 1][8] = $mod;
            } else {
                $this->modules[$this->moduleCount - 15 + $i][8] = $mod;
            }

            if ($i < 8) {
                $this->modules[8][$this->moduleCount - $i - 1] = $mod;
            } elseif ($i < 9) {
                $this->modules[8][15 - $i - 1 + 1] = $mod;
            } else {
                $this->modules[8][15 - $i - 1] = $mod;
            }
        }

        $this->modules[$this->moduleCount - 8][8] = ! $test;
    }

    private function mapData(array $data, int $maskPattern): void
    {
        $inc = -1;
        $row = $this->moduleCount - 1;
        $bitIndex = 7;
        $byteIndex = 0;
        $totalBytes = count($data);

        for ($col = $this->moduleCount - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--;

            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    if ($this->modules[$row][$col - $c] === null) {
                        $dark = false;
                        if ($byteIndex < $totalBytes) {
                            $dark = ((($data[$byteIndex] >> $bitIndex) & 1) === 1);
                        }
                        $mask = QrUtil::getMask($maskPattern, $row, $col - $c);
                        if ($mask) {
                            $dark = ! $dark;
                        }
                        $this->modules[$row][$col - $c] = $dark;
                        $bitIndex--;
                        if ($bitIndex === -1) {
                            $byteIndex++;
                            $bitIndex = 7;
                        }
                    }
                }

                $row += $inc;
                if ($row < 0 || $this->moduleCount <= $row) {
                    $row -= $inc;
                    $inc = - $inc;
                    break;
                }
            }
        }
    }

    public static function createData(int $typeNumber, int $errorCorrectLevel, array $dataList): array
    {
        $rsBlocks = QrRSBlock::getRSBlocks($typeNumber, $errorCorrectLevel);
        $buffer = new QrBitBuffer();

        // 8bit byte mode indicator = 4 (0100)
        $buffer->put(4, 4);
        $buffer->put(count($dataList), QrUtil::getLengthInBits(4, $typeNumber));
        foreach ($dataList as $b) {
            $buffer->put($b, 8);
        }

        // Total data capacity in bits
        $totalDataCount = 0;
        foreach ($rsBlocks as $rs) {
            $totalDataCount += $rs->getDataCount();
        }

        if ($buffer->getLengthInBits() > $totalDataCount * 8) {
            throw new \RuntimeException("Code length overflow. ({$buffer->getLengthInBits()} > " . ($totalDataCount * 8) . ")");
        }

        // Terminator: up to 4 zero bits
        if ($buffer->getLengthInBits() + 4 <= $totalDataCount * 8) {
            $buffer->put(0, 4);
        }

        // Pad to byte boundary
        while ($buffer->getLengthInBits() % 8 !== 0) {
            $buffer->putBit(false);
        }

        // Pad bytes 0xEC and 0x11
        while (true) {
            if ($buffer->getLengthInBits() >= $totalDataCount * 8) {
                break;
            }
            $buffer->put(0xEC, 8);
            if ($buffer->getLengthInBits() >= $totalDataCount * 8) {
                break;
            }
            $buffer->put(0x11, 8);
        }

        return self::createBytes($buffer, $rsBlocks);
    }

    private static function createBytes(QrBitBuffer $buffer, array $rsBlocks): array
    {
        $offset = 0;
        $maxDcCount = 0;
        $maxEcCount = 0;

        $dcdata = [];
        $ecdata = [];

        $blockCount = count($rsBlocks);
        for ($r = 0; $r < $blockCount; $r++) {
            $dcCount = $rsBlocks[$r]->getDataCount();
            $ecCount = $rsBlocks[$r]->getTotalCount() - $dcCount;

            $maxDcCount = max($maxDcCount, $dcCount);
            $maxEcCount = max($maxEcCount, $ecCount);

            $dcdata[$r] = [];
            for ($i = 0; $i < $dcCount; $i++) {
                $dcdata[$r][$i] = 0xFF & $buffer->getBuffer()[$i + $offset];
            }
            $offset += $dcCount;

            $rsPoly = QrUtil::getErrorCorrectPolynomial($ecCount);
            $rawPoly = new QrPolynomial($dcdata[$r], $rsPoly->getLength() - 1);
            $modPoly = $rawPoly->mod($rsPoly);

            $ecdata[$r] = [];
            $modLen = $rsPoly->getLength() - 1;
            for ($i = 0; $i < $modLen; $i++) {
                $modIndex = $i + $modPoly->getLength() - $modLen;
                $ecdata[$r][$i] = ($modIndex >= 0) ? $modPoly->get($modIndex) : 0;
            }
        }

        $data = [];
        for ($i = 0; $i < $maxDcCount; $i++) {
            for ($r = 0; $r < $blockCount; $r++) {
                if ($i < count($dcdata[$r])) {
                    $data[] = $dcdata[$r][$i];
                }
            }
        }

        for ($i = 0; $i < $maxEcCount; $i++) {
            for ($r = 0; $r < $blockCount; $r++) {
                if ($i < count($ecdata[$r])) {
                    $data[] = $ecdata[$r][$i];
                }
            }
        }

        return $data;
    }
}

class QrPolynomial
{
    private array $num;

    public function __construct(array $num, int $shift = 0)
    {
        $offset = 0;
        $count = count($num);
        while ($offset < $count && $num[$offset] === 0) {
            $offset++;
        }

        $this->num = array_fill(0, $count - $offset + $shift, 0);
        for ($i = 0; $i < $count - $offset; $i++) {
            $this->num[$i] = $num[$i + $offset];
        }
    }

    public function get(int $index): int
    {
        return $this->num[$index];
    }

    public function getLength(): int
    {
        return count($this->num);
    }

    public function multiply(QrPolynomial $e): QrPolynomial
    {
        $lenA = $this->getLength();
        $lenB = $e->getLength();
        $num = array_fill(0, $lenA + $lenB - 1, 0);

        for ($i = 0; $i < $lenA; $i++) {
            for ($j = 0; $j < $lenB; $j++) {
                $num[$i + $j] ^= QrUtil::gexp(QrUtil::glog($this->get($i)) + QrUtil::glog($e->get($j)));
            }
        }

        return new QrPolynomial($num);
    }

    public function mod(QrPolynomial $e): QrPolynomial
    {
        if ($this->getLength() - $e->getLength() < 0) {
            return $this;
        }

        $ratio = QrUtil::glog($this->get(0)) - QrUtil::glog($e->get(0));
        $num = $this->num;
        $eLen = $e->getLength();

        for ($i = 0; $i < $eLen; $i++) {
            $num[$i] ^= QrUtil::gexp(QrUtil::glog($e->get($i)) + $ratio);
        }

        $next = new QrPolynomial($num);
        return $next->mod($e);
    }
}

class QrBitBuffer
{
    private array $buffer = [];
    private int $length = 0;

    public function getBuffer(): array
    {
        return $this->buffer;
    }

    public function getLengthInBits(): int
    {
        return $this->length;
    }

    public function put(int $num, int $length): void
    {
        for ($i = 0; $i < $length; $i++) {
            $this->putBit((($num >> ($length - $i - 1)) & 1) === 1);
        }
    }

    public function putBit(bool $bit): void
    {
        $bufIndex = (int) floor($this->length / 8);
        if (count($this->buffer) <= $bufIndex) {
            $this->buffer[] = 0;
        }
        if ($bit) {
            $this->buffer[$bufIndex] |= (0x80 >> ($this->length % 8));
        }
        $this->length++;
    }
}

class QrRSBlock
{
    private int $totalCount;
    private int $dataCount;

    public function __construct(int $totalCount, int $dataCount)
    {
        $this->totalCount = $totalCount;
        $this->dataCount = $dataCount;
    }

    public function getDataCount(): int
    {
        return $this->dataCount;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    private static array $RS_BLOCK_TABLE = [
        // 1
        [1, 26, 19], [1, 26, 16], [1, 26, 13], [1, 26, 9],
        // 2
        [1, 44, 34], [1, 44, 28], [1, 44, 22], [1, 44, 16],
        // 3
        [1, 70, 55], [1, 70, 44], [2, 35, 17], [2, 35, 13],
        // 4
        [1, 100, 80], [2, 50, 32], [2, 50, 24], [4, 25, 9],
        // 5
        [1, 134, 108], [2, 67, 43], [2, 33, 15, 2, 34, 16], [2, 33, 11, 2, 34, 12],
        // 6
        [2, 86, 68], [4, 43, 27], [4, 43, 19], [4, 43, 15],
        // 7
        [2, 98, 78], [4, 49, 31], [2, 32, 14, 4, 33, 15], [4, 39, 13, 1, 40, 14],
        // 8
        [2, 121, 97], [2, 60, 38, 2, 61, 39], [4, 40, 18, 2, 41, 19], [4, 40, 14, 2, 41, 15],
        // 9
        [2, 146, 116], [3, 58, 36, 2, 59, 37], [4, 36, 16, 4, 37, 17], [4, 36, 12, 4, 37, 13],
        // 10
        [2, 86, 68, 2, 87, 69], [4, 69, 43, 1, 70, 44], [6, 43, 19, 2, 44, 20], [6, 43, 15, 2, 44, 16],
    ];

    public static function getRSBlocks(int $typeNumber, int $errorCorrectLevel): array
    {
        $tableIndex = ($typeNumber - 1) * 4;
        switch ($errorCorrectLevel) {
            case QrCodeModel::ERROR_CORRECT_L: $tableIndex += 0; break;
            case QrCodeModel::ERROR_CORRECT_M: $tableIndex += 1; break;
            case QrCodeModel::ERROR_CORRECT_Q: $tableIndex += 2; break;
            case QrCodeModel::ERROR_CORRECT_H: $tableIndex += 3; break;
        }

        $row = self::$RS_BLOCK_TABLE[$tableIndex] ?? null;
        if (! $row) {
            throw new \RuntimeException("Invalid RS block for type {$typeNumber} and level {$errorCorrectLevel}");
        }

        $list = [];
        $triplets = count($row) / 3;
        for ($i = 0; $i < $triplets; $i++) {
            $count = $row[$i * 3 + 0];
            $totalCount = $row[$i * 3 + 1];
            $dataCount = $row[$i * 3 + 2];
            for ($j = 0; $j < $count; $j++) {
                $list[] = new QrRSBlock($totalCount, $dataCount);
            }
        }

        return $list;
    }
}

class QrUtil
{
    private static array $EXP_TABLE = [];
    private static array $LOG_TABLE = [];
    private static bool $tablesInitialized = false;

    private static function initTables(): void
    {
        if (self::$tablesInitialized) return;
        self::$EXP_TABLE = array_fill(0, 256, 0);
        self::$LOG_TABLE = array_fill(0, 256, 0);

        for ($i = 0; $i < 8; $i++) {
            self::$EXP_TABLE[$i] = 1 << $i;
        }
        for ($i = 8; $i < 256; $i++) {
            self::$EXP_TABLE[$i] = self::$EXP_TABLE[$i - 4]
                ^ self::$EXP_TABLE[$i - 5]
                ^ self::$EXP_TABLE[$i - 6]
                ^ self::$EXP_TABLE[$i - 8];
        }
        for ($i = 0; $i < 255; $i++) {
            self::$LOG_TABLE[self::$EXP_TABLE[$i]] = $i;
        }
        self::$tablesInitialized = true;
    }

    public static function glog(int $n): int
    {
        self::initTables();
        if ($n < 1) throw new \RuntimeException("glog({$n})");
        return self::$LOG_TABLE[$n];
    }

    public static function gexp(int $n): int
    {
        self::initTables();
        while ($n < 0) $n += 255;
        while ($n >= 255) $n -= 255;
        return self::$EXP_TABLE[$n];
    }

    public static function getBCHTypeInfo(int $data): int
    {
        $d = $data << 10;
        while (self::getBCHDigit($d) - self::getBCHDigit(1335) >= 0) {
            $d ^= (1335 << (self::getBCHDigit($d) - self::getBCHDigit(1335)));
        }
        return (($data << 10) | $d) ^ 21522;
    }

    public static function getBCHTypeNumber(int $data): int
    {
        $d = $data << 12;
        while (self::getBCHDigit($d) - self::getBCHDigit(7973) >= 0) {
            $d ^= (7973 << (self::getBCHDigit($d) - self::getBCHDigit(7973)));
        }
        return ($data << 12) | $d;
    }

    private static function getBCHDigit(int $data): int
    {
        $digit = 0;
        while ($data !== 0) {
            $digit++;
            $data >>= 1;
        }
        return $digit;
    }

    private static array $PATTERN_POSITION_TABLE = [
        [],
        [6, 18],
        [6, 22],
        [6, 26],
        [6, 30],
        [6, 34],
        [6, 22, 38],
        [6, 24, 42],
        [6, 26, 46],
        [6, 28, 50],
        [6, 30, 54],
    ];

    public static function getPatternPosition(int $typeNumber): array
    {
        return self::$PATTERN_POSITION_TABLE[$typeNumber - 1] ?? [];
    }

    public static function getMask(int $maskPattern, int $i, int $j): bool
    {
        return match ($maskPattern) {
            0 => (($i + $j) % 2 === 0),
            1 => ($i % 2 === 0),
            2 => ($j % 3 === 0),
            3 => (($i + $j) % 3 === 0),
            4 => (((int) floor($i / 2) + (int) floor($j / 3)) % 2 === 0),
            5 => (($i * $j) % 2 + ($i * $j) % 3 === 0),
            6 => ((($i * $j) % 2 + ($i * $j) % 3) % 2 === 0),
            7 => ((($i * $j) % 3 + ($i + $j) % 2) % 2 === 0),
            default => throw new \RuntimeException("Invalid mask pattern: {$maskPattern}"),
        };
    }

    public static function getErrorCorrectPolynomial(int $errorCorrectLength): QrPolynomial
    {
        $a = new QrPolynomial([1]);
        for ($i = 0; $i < $errorCorrectLength; $i++) {
            $a = $a->multiply(new QrPolynomial([1, self::gexp($i)]));
        }
        return $a;
    }

    public static function getLengthInBits(int $mode, int $type): int
    {
        // 8-bit byte mode = 4
        if (1 <= $type && $type <= 9) {
            return 8;
        }
        return 16;
    }

    public static function getLostPoint(QrCodeModel $qrCode): int
    {
        $moduleCount = $qrCode->getModuleCount();
        $lostPoint = 0;

        // Level 1
        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                $sameCount = 0;
                $dark = $qrCode->isDark($row, $col);
                for ($r = -1; $r <= 1; $r++) {
                    if ($row + $r < 0 || $moduleCount <= $row + $r) continue;
                    for ($c = -1; $c <= 1; $c++) {
                        if ($col + $c < 0 || $moduleCount <= $col + $c) continue;
                        if ($r === 0 && $c === 0) continue;
                        if ($dark === $qrCode->isDark($row + $r, $col + $c)) {
                            $sameCount++;
                        }
                    }
                }
                if ($sameCount > 5) {
                    $lostPoint += (3 + $sameCount - 5);
                }
            }
        }

        // Level 2 (2x2 blocks)
        for ($row = 0; $row < $moduleCount - 1; $row++) {
            for ($col = 0; $col < $moduleCount - 1; $col++) {
                $count = 0;
                if ($qrCode->isDark($row, $col)) $count++;
                if ($qrCode->isDark($row + 1, $col)) $count++;
                if ($qrCode->isDark($row, $col + 1)) $count++;
                if ($qrCode->isDark($row + 1, $col + 1)) $count++;
                if ($count === 0 || $count === 4) {
                    $lostPoint += 3;
                }
            }
        }

        return $lostPoint;
    }
}
