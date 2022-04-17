<?php

namespace miyasinarafat\QRCode;

use miyasinarafat\QRCode\Consts\QRCodeEncodingModeConst;
use RuntimeException;

class QRCodeInputSplit
{
    public string $dataStr = '';

    public function __construct(
        string $dataStr,
        public QRCodeInput $input,
        public int $modeHint
    ) {
        $this->dataStr = $dataStr;
    }

    /**
     * @param string $str
     * @param int $pos
     * @return bool
     */
    public static function isdigitat(string $str, int $pos): bool
    {
        if ($pos >= strlen($str)) {
            return false;
        }

        return ((ord($str[$pos]) >= ord('0')) && (ord($str[$pos]) <= ord('9')));
    }

    /**
     * @param string $str
     * @param int $pos
     * @return bool
     */
    public static function isalnumat(string $str, int $pos): bool
    {
        if ($pos >= strlen($str)) {
            return false;
        }

        return (QRCodeInput::lookAnTable(ord($str[$pos])) >= 0);
    }

    /**
     * @param int $pos
     * @return int
     */
    public function identifyMode(int $pos): int
    {
        if ($pos >= strlen($this->dataStr)) {
            return QRCodeEncodingModeConst::MODE_NUL;
        }

        $c = $this->dataStr[$pos];

        if (self::isdigitat($this->dataStr, $pos)) {
            return QRCodeEncodingModeConst::MODE_NUM;
        }

        if (self::isalnumat($this->dataStr, $pos)) {
            return QRCodeEncodingModeConst::MODE_AN;
        }

        if (($this->modeHint === QRCodeEncodingModeConst::MODE_KANJI) && $pos + 1 < strlen($this->dataStr)) {
            $d = $this->dataStr[$pos + 1];
            $word = (ord($c) << 8) | ord($d);
            if (($word >= 0x8140 && $word <= 0x9ffc) || ($word >= 0xe040 && $word <= 0xebbf)) {
                return QRCodeEncodingModeConst::MODE_KANJI;
            }
        }

        return QRCodeEncodingModeConst::MODE_8;
    }

    /**
     * @return mixed
     */
    public function eatNum(): mixed
    {
        $ln = QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_NUM, $this->input->getVersion());

        $p = 0;
        while (self::isdigitat($this->dataStr, $p)) {
            $p++;
        }

        $run = $p;
        $mode = $this->identifyMode($p);

        if ($mode === QRCodeEncodingModeConst::MODE_8) {
            $dif = QRCodeInput::estimateBitsModeNum($run) + 4 + $ln
                + QRCodeInput::estimateBitsMode8(1)         // + 4 + l8
                - QRCodeInput::estimateBitsMode8($run + 1); // - 4 - l8

            if ($dif > 0) {
                return $this->eat8();
            }
        }
        if ($mode === QRCodeEncodingModeConst::MODE_AN) {
            $dif = QRCodeInput::estimateBitsModeNum($run) + 4 + $ln
                + QRCodeInput::estimateBitsModeAn(1)        // + 4 + la
                - QRCodeInput::estimateBitsModeAn($run + 1);// - 4 - la

            if ($dif > 0) {
                return $this->eatAn();
            }
        }

        $ret = $this->input->append(QRCodeEncodingModeConst::MODE_NUM, $run, str_split($this->dataStr));
        if ($ret < 0) {
            return -1;
        }

        return $run;
    }

    /**
     * @return mixed
     */
    public function eatAn(): mixed
    {
        $la = QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_AN, $this->input->getVersion());
        $ln = QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_NUM, $this->input->getVersion());

        $p = 0;

        while (self::isalnumat($this->dataStr, $p)) {
            if (self::isdigitat($this->dataStr, $p)) {
                $q = $p;
                while (self::isdigitat($this->dataStr, $q)) {
                    $q++;
                }

                $dif = QRCodeInput::estimateBitsModeAn($p) // + 4 + la
                    + QRCodeInput::estimateBitsModeNum($q - $p) + 4 + $ln
                    - QRCodeInput::estimateBitsModeAn($q); // - 4 - la

                if ($dif < 0) {
                    break;
                }

                $p = $q;
            } else {
                $p++;
            }
        }

        $run = $p;

        if (! self::isalnumat($this->dataStr, $p)) {
            $dif = QRCodeInput::estimateBitsModeAn($run) + 4 + $la
                + QRCodeInput::estimateBitsMode8(1) // + 4 + l8
                - QRCodeInput::estimateBitsMode8($run + 1); // - 4 - l8

            if ($dif > 0) {
                return $this->eat8();
            }
        }

        $ret = $this->input->append(QRCodeEncodingModeConst::MODE_AN, $run, str_split($this->dataStr));
        if ($ret < 0) {
            return -1;
        }

        return $run;
    }

    /**
     * @return int
     */
    public function eatKanji(): int
    {
        $p = 0;

        while ($this->identifyMode($p) === QRCodeEncodingModeConst::MODE_KANJI) {
            $p += 2;
        }

        $ret = $this->input->append(QRCodeEncodingModeConst::MODE_KANJI, $p, str_split($this->dataStr));
        if ($ret < 0) {
            return -1;
        }

        return $ret;
    }

    /**
     * @return mixed
     */
    public function eat8(): mixed
    {
        $la = QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_AN, $this->input->getVersion());
        $ln = QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_NUM, $this->input->getVersion());

        $p = 1;
        $dataStrLen = strlen($this->dataStr);

        while ($p < $dataStrLen) {
            $mode = $this->identifyMode($p);
            if ($mode === QRCodeEncodingModeConst::MODE_KANJI) {
                break;
            }

            if ($mode === QRCodeEncodingModeConst::MODE_NUM) {
                $q = $p;
                while (self::isdigitat($this->dataStr, $q)) {
                    $q++;
                }

                $dif = QRCodeInput::estimateBitsMode8($p) // + 4 + l8
                    + QRCodeInput::estimateBitsModeNum($q - $p) + 4 + $ln
                    - QRCodeInput::estimateBitsMode8($q); // - 4 - l8

                if ($dif < 0) {
                    break;
                }

                $p = $q;
            } elseif ($mode === QRCodeEncodingModeConst::MODE_AN) {
                $q = $p;
                while (self::isalnumat($this->dataStr, $q)) {
                    $q++;
                }

                $dif = QRCodeInput::estimateBitsMode8($p)  // + 4 + l8
                    + QRCodeInput::estimateBitsModeAn($q - $p) + 4 + $la
                    - QRCodeInput::estimateBitsMode8($q); // - 4 - l8

                if ($dif < 0) {
                    break;
                }

                $p = $q;
            } else {
                $p++;
            }
        }

        $run = $p;
        $ret = $this->input->append(QRCodeEncodingModeConst::MODE_8, $run, str_split($this->dataStr));

        if ($ret < 0) {
            return -1;
        }

        return $run;
    }

    /**
     * @return int
     */
    public function splitString(): int
    {
        while ($this->dataStr !== '') {
            $mode = $this->identifyMode(0);

            switch ($mode) {
                case QRCodeEncodingModeConst::MODE_NUM:
                    $length = $this->eatNum();
                    break;
                case QRCodeEncodingModeConst::MODE_AN:
                    $length = $this->eatAn();
                    break;
                case QRCodeEncodingModeConst::MODE_KANJI:
                    if ($mode === QRCodeEncodingModeConst::MODE_KANJI) {
                        $length = $this->eatKanji();
                    } else {
                        $length = $this->eat8();
                    }
                    break;
                default:
                    $length = $this->eat8();
                    break;
            }

            if ($length === 0) {
                return 0;
            }

            if ($length < 0) {
                return -1;
            }

            $this->dataStr = substr($this->dataStr, $length);
        }

        return 0;
    }

    /**
     * @return string
     */
    public function toUpper(): string
    {
        $stringLen = strlen($this->dataStr);
        $p = 0;

        while ($p < $stringLen) {
            $mode = $this->identifyMode((int)substr($this->dataStr, $p));

            if ($mode === QRCodeEncodingModeConst::MODE_KANJI) {
                $p += 2;
            } else {
                if (ord($this->dataStr[$p]) >= ord('a') && ord($this->dataStr[$p]) <= ord('z')) {
                    $this->dataStr[$p] = chr(ord($this->dataStr[$p]) - 32);
                }

                $p++;
            }
        }

        return $this->dataStr;
    }

    /**
     * @param string $string
     * @param QRCodeInput $input
     * @param int $modeHint
     * @param bool $casesensitive
     * @return int
     */
    public static function splitStringToQRCodeInput(string $string, QRCodeInput $input, int $modeHint, bool $casesensitive = true): int
    {
        if ($string === '\0' || $string === '') {
            throw new RuntimeException('empty string!!!');
        }

        $split = new self($string, $input, $modeHint);

        if (! $casesensitive) {
            $split->toUpper();
        }

        return $split->splitString();
    }
}
