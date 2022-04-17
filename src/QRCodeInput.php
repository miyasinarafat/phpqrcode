<?php

namespace miyasinarafat\QRCode;

use Exception;
use miyasinarafat\QRCode\Consts\QRCodeEncodingModeConst;
use miyasinarafat\QRCode\Enums\QRCodeErrorCorrectionEnum;
use miyasinarafat\QRCode\Enums\QRCodeSpecificationEnum;
use RuntimeException;

class QRCodeInput
{
    public array $items = [];

    private int $level;

    public static array $anTable = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,
        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 44, -1, -1, -1, -1, -1,
        -1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
        25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
    ];

    public function __construct(
        private int $version = 0,
        ?int $level = null,
    ) {
        if ($version < 0 || $version > QRCodeSpecificationEnum::VERSION_MAX->value || $level > QRCodeErrorCorrectionEnum::LEVEL_H->value) {
            throw new RuntimeException('Invalid version no');
        }

        $this->level = $level ?? QRCodeErrorCorrectionEnum::LEVEL_L->value;
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @param int $version
     * @return void
     */
    public function setVersion(int $version): void
    {
        if ($version < 0 || $version > QRCodeSpecificationEnum::VERSION_MAX->value) {
            throw new RuntimeException('Invalid version no');
        }

        $this->version = $version;
    }

    /**
     * @return int
     */
    public function getErrorCorrectionLevel(): int
    {
        return $this->level;
    }

    /**
     * @param mixed $mode
     * @param int $size
     * @param array $data
     * @return int
     */
    public function append(mixed $mode, int $size, array $data): int
    {
        try {
            $entry = new QRCodeInputItem($mode, $size, $data);
            $this->items[] = $entry;

            return 0;
        } catch (Exception) {
            return -1;
        }
    }

    /**
     * @param int $size
     * @param mixed $data
     * @return bool
     */
    public static function checkModeNum(int $size, mixed $data): bool
    {
        for ($i = 0; $i < $size; $i++) {
            if ((ord($data[$i]) < ord('0')) || (ord($data[$i]) > ord('9'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $size
     * @return float|int
     */
    public static function estimateBitsModeNum(int $size): float|int
    {
        $w = $size / 3;
        $bits = $w * 10;

        switch ($size - $w * 3) {
            case 1:
                $bits += 4;
                break;
            case 2:
                $bits += 7;
                break;
            default:
                break;
        }

        return $bits;
    }

    /**
     * @param int $c
     * @return mixed
     */
    public static function lookAnTable(int $c): mixed
    {
        return (($c > 127) ? -1 : self::$anTable[$c]);
    }

    /**
     * @param int $size
     * @param array $data
     * @return bool
     */
    public static function checkModeAn(int $size, array $data): bool
    {
        for ($i = 0; $i < $size; $i++) {
            if (self::lookAnTable(ord($data[$i])) === -1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $size
     * @return float|int
     */
    public static function estimateBitsModeAn(int $size): float|int
    {
        $w = (int)($size / 2);
        $bits = $w * 11;

        if ($size & 1) {
            $bits += 6;
        }

        return $bits;
    }

    /**
     * @param int $size
     * @return float|int
     */
    public static function estimateBitsMode8(int $size): float|int
    {
        return $size * 8;
    }

    /**
     * @param int $size
     * @return int
     */
    public static function estimateBitsModeKanji(int $size): int
    {
        return (int)(($size / 2) * 13);
    }

    /**
     * @param int $size
     * @param array $data
     * @return bool
     */
    public static function checkModeKanji(int $size, array $data): bool
    {
        if ($size & 1) {
            return false;
        }

        for ($i = 0; $i < $size; $i += 2) {
            $val = (ord($data[$i]) << 8) | ord($data[$i + 1]);
            if ($val < 0x8140
                || ($val > 0x9ffc && $val < 0xe040)
                || $val > 0xebbf) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validation
     *
     * @param mixed $mode
     * @param int $size
     * @param mixed $data
     * @return bool
     */
    public static function check(mixed $mode, int $size, mixed $data): bool
    {
        if ($size <= 0) {
            return false;
        }

        switch ($mode) {
            case QRCodeEncodingModeConst::MODE_NUM:
                return self::checkModeNum($size, $data);
            case QRCodeEncodingModeConst::MODE_AN:
                return self::checkModeAn($size, $data);
            case QRCodeEncodingModeConst::MODE_KANJI:
                return self::checkModeKanji($size, $data);
            case QRCodeEncodingModeConst::MODE_STRUCTURE:
            case QRCodeEncodingModeConst::MODE_8:
                return true;
            default:
                break;
        }

        return false;
    }

    /**
     * @param int $version
     * @return float|int
     */
    public function estimateBitStreamSize(int $version): float|int
    {
        $bits = 0;

        /** @var QRCodeInputItem $item */
        foreach ($this->items as $item) {
            $bits += $item->estimateBitStreamSizeOfEntry($version);
        }

        return $bits;
    }

    /**
     * @return int
     */
    public function estimateVersion(): int
    {
        $version = 0;
        $prev = 0;

        do {
            $prev = $version;
            $bits = $this->estimateBitStreamSize($prev);
            $version = QRCodeSpecification::getMinimumVersion((int)(($bits + 7) / 8), $this->level);

            if ($version < 0) {
                return -1;
            }
        } while ($version > $prev);

        return $version;
    }

    /**
     * @return int
     */
    public function createBitStream(): int
    {
        $total = 0;

        /** @var QRCodeInputItem $item */
        foreach ($this->items as $item) {
            $bits = $item->encodeBitStream($this->version);

            if ($bits < 0) {
                return -1;
            }

            $total += $bits;
        }

        return $total;
    }

    /**
     * @return int
     */
    public function convertData(): int
    {
        $ver = $this->estimateVersion();
        if ($ver > $this->getVersion()) {
            $this->setVersion($ver);
        }

        for (; ;) {
            $bits = $this->createBitStream();

            if ($bits < 0) {
                return -1;
            }

            $ver = QRCodeSpecification::getMinimumVersion((int)(($bits + 7) / 8), $this->level);
            if ($ver < 0) {
                throw new RuntimeException('WRONG VERSION');
            }

            if ($ver > $this->getVersion()) {
                $this->setVersion($ver);
            } else {
                break;
            }
        }

        return 0;
    }

    /**
     * @param QRCodeBitStream $bstream
     * @return int
     */
    public function appendPaddingBit(QRCodeBitStream $bstream): int
    {
        $bits = $bstream->size();
        $maxwords = QRCodeSpecification::getDataLength($this->version, $this->level);
        $maxbits = $maxwords * 8;

        if ($maxbits === $bits) {
            return 0;
        }

        if ($maxbits - $bits < 5) {
            return $bstream->appendNum($maxbits - $bits, 0);
        }

        $bits += 4;
        $words = (int)(($bits + 7) / 8);

        $padding = new QRCodeBitStream();
        $ret = $padding->appendNum($words * 8 - $bits + 4, 0);

        if ($ret < 0) {
            return $ret;
        }

        $padlen = $maxwords - $words;

        if ($padlen > 0) {
            $padbuf = [];
            for ($i = 0; $i < $padlen; $i++) {
                $padbuf[$i] = ($i & 1) ? 0x11 : 0xec;
            }

            $ret = $padding->appendBytes($padlen, $padbuf);

            if ($ret < 0) {
                return $ret;
            }
        }

        return $bstream->append($padding);
    }

    /**
     * @return QRCodeBitStream|null
     */
    public function mergeBitStream(): ?QRCodeBitStream
    {
        if ($this->convertData() < 0) {
            return null;
        }

        $bstream = new QRCodeBitStream();

        /** @var QRCodeInputItem $item */
        foreach ($this->items as $item) {
            $ret = $bstream->append($item->bstream);
            if ($ret < 0) {
                return null;
            }
        }

        return $bstream;
    }

    /**
     * @return QRCodeBitStream|null
     */
    public function getBitStream(): ?QRCodeBitStream
    {
        $bstream = $this->mergeBitStream();

        if (is_null($bstream)) {
            return null;
        }

        $ret = $this->appendPaddingBit($bstream);

        if ($ret < 0) {
            return null;
        }

        return $bstream;
    }

    /**
     * @return array|null
     */
    public function getByteStream(): ?array
    {
        $bstream = $this->getBitStream();

        if (is_null($bstream)) {
            return null;
        }

        return $bstream->toByte();
    }
}
