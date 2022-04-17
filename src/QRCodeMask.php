<?php

namespace miyasinarafat\QRCode;

use Exception;
use miyasinarafat\QRCode\Consts\QRCodeEncoderConfigConst;
use miyasinarafat\QRCode\Consts\QRCodeMaskingConst;
use miyasinarafat\QRCode\Enums\QRCodeSpecificationEnum;
use RuntimeException;

class QRCodeMask
{
    public array $runLength = [];

    public function __construct()
    {
        $this->runLength = array_fill(0, QRCodeSpecificationEnum::WIDTH_MAX->value + 1, 0);
    }

    /**
     * @param int $width
     * @param array $frame
     * @param mixed $mask
     * @param int $level
     * @return int
     */
    public function writeFormatInformation(int $width, array &$frame, mixed $mask, int $level): int
    {
        $blacks = 0;
        $format = QRCodeSpecification::getFormatInfo($mask, $level);

        for ($i = 0; $i < 8; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }

            $frame[8][$width - 1 - $i] = chr($v);

            if ($i < 6) {
                $frame[$i][8] = chr($v);
            } else {
                $frame[$i + 1][8] = chr($v);
            }

            $format >>= 1;
        }

        for ($i = 0; $i < 7; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }

            $frame[$width - 7 + $i][8] = chr($v);

            if ($i === 0) {
                $frame[8][7] = chr($v);
            } else {
                $frame[8][6 - $i] = chr($v);
            }

            $format >>= 1;
        }

        return $blacks;
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask0(int $x, int $y): int
    {
        return ($x + $y) & 1;
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask1(int $x, int $y): int
    {
        return ($y & 1);
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask2(int $x, int $y): int
    {
        return ($x % 3);
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask3(int $x, int $y): int
    {
        return ($x + $y) % 3;
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask4(int $x, int $y): int
    {
        return (((int)($y / 2)) + ((int)($x / 3))) & 1;
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask5(int $x, int $y): int
    {
        return (($x * $y) & 1) + ($x * $y) % 3;
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask6(int $x, int $y): int
    {
        return ((($x * $y) & 1) + ($x * $y) % 3) & 1;
    }

    /**
     * @param int $x
     * @param int $y
     * @return int
     */
    public function mask7(int $x, int $y): int
    {
        return ((($x * $y) % 3) + (($x + $y) & 1)) & 1;
    }

    /**
     * @param int $maskNo
     * @param int $width
     * @param array $frame
     * @return array
     */
    private function generateMaskNo(int $maskNo, int $width, array $frame): array
    {
        $bitMask = array_fill(0, $width, array_fill(0, $width, 0));

        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (ord($frame[$y][$x]) & 0x80) {
                    $bitMask[$y][$x] = 0;
                } else {
                    $maskFunc = $this->{'mask' . $maskNo}($x, $y);
                    $bitMask[$y][$x] = ($maskFunc === 0) ? 1 : 0;
                }
            }
        }

        return $bitMask;
    }

    /**
     * @param array $bitFrame
     * @return bool|string
     */
    public static function serial(array $bitFrame): bool|string
    {
        $codeArr = [];

        foreach ($bitFrame as $line) {
            $codeArr[] = implode('', $line);
        }

        return gzcompress(implode("\n", $codeArr), 9);
    }

    /**
     * @param string $code
     * @return array
     */
    public static function unserial(string $code): array
    {
        $codeArr = [];
        $codeLines = explode("\n", gzuncompress($code));

        foreach ($codeLines as $line) {
            $codeArr[] = str_split($line);
        }

        return $codeArr;
    }

    /**
     * @param int $maskNo
     * @param int $width
     * @param array $s
     * @param array $d
     * @param bool $maskGenOnly
     * @return int
     */
    public function makeMaskNo(int $maskNo, int $width, array $s, array &$d, bool $maskGenOnly = false): int
    {
        $b = 0;
        $bitMask = [];

        $fileName = sprintf(
            '%smask_%smask_%s_%s.dat',
            (string)QRCodeEncoderConfigConst::QR_CACHE_DIR,
            $maskNo . DIRECTORY_SEPARATOR,
            $width,
            $maskNo
        );

        if (QRCodeEncoderConfigConst::QR_CACHEABLE) {
            if (file_exists($fileName)) {
                $bitMask = self::unserial(file_get_contents($fileName));
            } else {
                $bitMask = $this->generateMaskNo($maskNo, $width, $s);
                $fileName = sprintf('%smask_%s', (string)QRCodeEncoderConfigConst::QR_CACHE_DIR, $maskNo);

                if (! file_exists($fileName) && ! mkdir($fileName) && ! is_dir($fileName)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $fileName));
                }

                file_put_contents($fileName, (string)self::serial($bitMask));
            }
        } else {
            $bitMask = $this->generateMaskNo($maskNo, $width, $s);
        }

        if ($maskGenOnly) {
            return 0;
        }

        $d = $s;

        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($bitMask[$y][$x] === 1) {
                    $d[$y][$x] = chr(ord($s[$y][$x]) ^ $bitMask[$y][$x]);
                }
                $b += (ord($d[$y][$x]) & 1);
            }
        }

        return $b;
    }

    /**
     * @param int $width
     * @param array $frame
     * @param int $maskNo
     * @param int $level
     * @return array
     */
    public function makeMask(int $width, array $frame, int $maskNo, int $level): array
    {
        $masked = array_fill(0, $width, str_repeat("\0", $width));

        $this->makeMaskNo($maskNo, $width, $frame, $masked);
        $this->writeFormatInformation($width, $masked, $maskNo, $level);

        return $masked;
    }

    /**
     * @param int $length
     * @return mixed
     */
    public function calcN1N3(int $length): mixed
    {
        $demerit = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($this->runLength[$i] >= 5) {
                $demerit += (QRCodeMaskingConst::N1 + ($this->runLength[$i] - 5));
            }

            if (($i & 1) && ($i >= 3) && ($i < ($length - 2)) && ($this->runLength[$i] % 3 === 0)) {
                $fact = (int)($this->runLength[$i] / 3);

                if (($this->runLength[$i - 2] === $fact) &&
                    ($this->runLength[$i - 1] === $fact) &&
                    ($this->runLength[$i + 1] === $fact) &&
                    ($this->runLength[$i + 2] === $fact)) {
                    if (($this->runLength[$i - 3] < 0) || ($this->runLength[$i - 3] >= (4 * $fact))) {
                        $demerit += QRCodeMaskingConst::N3;
                    } elseif ((($i + 3) >= $length) || ($this->runLength[$i + 3] >= (4 * $fact))) {
                        $demerit += QRCodeMaskingConst::N3;
                    }
                }
            }
        }

        return $demerit;
    }

    /**
     * @param int $width
     * @param array $frame
     * @return mixed
     */
    public function evaluateSymbol(int $width, array $frame): mixed
    {
        $head = 0;
        $demerit = 0;

        for ($y = 0; $y < $width; $y++) {
            $head = 0;
            $this->runLength[0] = 1;

            $frameY = $frame[$y];
            $frameYM = [];

            if ($y > 0) {
                $frameYM = $frame[$y - 1];
            }

            for ($x = 0; $x < $width; $x++) {
                if (($x > 0) && ($y > 0)) {
                    $b22 = ord($frameY[$x]) & ord($frameY[$x - 1]) & ord($frameYM[$x]) & ord($frameYM[$x - 1]);
                    $w22 = ord($frameY[$x]) | ord($frameY[$x - 1]) | ord($frameYM[$x]) | ord($frameYM[$x - 1]);

                    if (($b22 | ($w22 ^ 1)) & 1) {
                        $demerit += QRCodeMaskingConst::N2;
                    }
                }

                if (($x === 0) && (ord($frameY[$x]) & 1)) {
                    $this->runLength[0] = -1;
                    $head = 1;
                    $this->runLength[$head] = 1;
                } elseif ($x > 0) {
                    if ((ord($frameY[$x]) ^ ord($frameY[$x - 1])) & 1) {
                        $head++;
                        $this->runLength[$head] = 1;
                    } else {
                        $this->runLength[$head]++;
                    }
                }
            }

            $demerit += $this->calcN1N3($head + 1);
        }

        for ($x = 0; $x < $width; $x++) {
            $head = 0;
            $this->runLength[0] = 1;

            for ($y = 0; $y < $width; $y++) {
                if ($y === 0 && (ord($frame[$y][$x]) & 1)) {
                    $this->runLength[0] = -1;
                    $head = 1;
                    $this->runLength[$head] = 1;
                } elseif ($y > 0) {
                    if ((ord($frame[$y][$x]) ^ ord($frame[$y - 1][$x])) & 1) {
                        $head++;
                        $this->runLength[$head] = 1;
                    } else {
                        $this->runLength[$head]++;
                    }
                }
            }

            $demerit += $this->calcN1N3($head + 1);
        }

        return $demerit;
    }

    /**
     * @param int $width
     * @param array $frame
     * @param int $level
     * @return array
     * @throws Exception
     */
    public function mask(int $width, array $frame, int $level): array
    {
        $minDemerit = PHP_INT_MAX;
        $bestMaskNum = 0;
        $bestMask = [];

        $checkedMasks = [0, 1, 2, 3, 4, 5, 6, 7];

        if ((bool)QRCodeEncoderConfigConst::QR_FIND_FROM_RANDOM !== false) {
            $howManuOut = 8 - (QRCodeEncoderConfigConst::QR_FIND_FROM_RANDOM % 9);
            for ($i = 0; $i < $howManuOut; $i++) {
                $remPos = random_int(0, count($checkedMasks) - 1);
                unset($checkedMasks[$remPos]);
                $checkedMasks = array_values($checkedMasks);
            }
        }

        $bestMask = $frame;

        foreach ($checkedMasks as $i) {
            $mask = array_fill(0, $width, str_repeat("\0", $width));

            $blacks = $this->makeMaskNo($i, $width, $frame, $mask);
            $blacks += $this->writeFormatInformation($width, $mask, $i, $level);
            $blacks = (int)(100 * $blacks / ($width * $width));
            $demerit = ((int)(abs($blacks - 50) / 5) * QRCodeMaskingConst::N4);
            $demerit += $this->evaluateSymbol($width, $mask);

            if ($demerit < $minDemerit) {
                $minDemerit = $demerit;
                $bestMask = $mask;
                $bestMaskNum = $i;
            }
        }

        return $bestMask;
    }
}
