<?php

namespace miyasinarafat\QRCode;

use Exception;
use miyasinarafat\QRCode\Consts\QRCodeEncoderConfigConst;
use miyasinarafat\QRCode\Consts\QRCodeEncodingModeConst;
use RuntimeException;

class QRCodeInputItem
{
    public array $data;

    public function __construct(
        public mixed $mode,
        public int $size,
        array $data,
        public ?QRCodeBitStream $bstream = null
    ) {
        $setData = array_slice($data, 0, $size);

        if (count($setData) < $size) {
            $setData = array_merge($setData, array_fill(0, $size - count($setData), 0));
        }

        if (! QRCodeInput::check($mode, $size, $setData)) {
            throw new RuntimeException('Error m:' . $mode . ',s:' . $size . ',d:' . implode(',', $setData));
        }

        $this->data = $setData;
    }

    /**
     * @param int $version
     * @return int
     */
    public function encodeModeNum(int $version): int
    {
        try {
            $words = (int)($this->size / 3);
            $bs = new QRCodeBitStream();

            $val = 0x1;
            $bs->appendNum(4, $val);
            $bs->appendNum(QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_NUM, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = (ord($this->data[$i * 3]) - ord('0')) * 100;
                $val += (ord($this->data[$i * 3 + 1]) - ord('0')) * 10;
                $val += (ord($this->data[$i * 3 + 2]) - ord('0'));
                $bs->appendNum(10, $val);
            }

            if ($this->size - $words * 3 === 1) {
                $val = ord($this->data[$words * 3]) - ord('0');
                $bs->appendNum(4, $val);
            } elseif ($this->size - $words * 3 === 2) {
                $val = (ord($this->data[$words * 3]) - ord('0')) * 10;
                $val += (ord($this->data[$words * 3 + 1]) - ord('0'));
                $bs->appendNum(7, $val);
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception) {
            return -1;
        }
    }

    /**
     * @param int $version
     * @return int
     */
    public function encodeModeAn(int $version): int
    {
        try {
            $words = (int)($this->size / 2);
            $bs = new QRCodeBitStream();

            $bs->appendNum(4, 0x02);
            $bs->appendNum(QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_AN, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = QRCodeInput::lookAnTable(ord($this->data[$i * 2])) * 45;
                $val += QRCodeInput::lookAnTable(ord($this->data[$i * 2 + 1]));

                $bs->appendNum(11, $val);
            }

            if ($this->size & 1) {
                $val = QRCodeInput::lookAnTable(ord($this->data[$words * 2]));
                $bs->appendNum(6, $val);
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception) {
            return -1;
        }
    }

    /**
     * @param int $version
     * @return int
     */
    public function encodeMode8(int $version): int
    {
        try {
            $bs = new QRCodeBitStream();

            $bs->appendNum(4, 0x4);
            $bs->appendNum(QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_8, $version), $this->size);

            for ($i = 0; $i < $this->size; $i++) {
                $bs->appendNum(8, ord($this->data[$i]));
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception) {
            return -1;
        }
    }

    /**
     * @param int $version
     * @return int
     */
    public function encodeModeKanji(int $version): int
    {
        try {
            $bs = new QRCodeBitStream();

            $bs->appendNum(4, 0x8);
            $bs->appendNum(QRCodeSpecification::lengthIndicator(QRCodeEncodingModeConst::MODE_KANJI, $version), (int)($this->size / 2));

            for ($i = 0; $i < $this->size; $i += 2) {
                $val = (ord($this->data[$i]) << 8) | ord($this->data[$i + 1]);
                if ($val <= 0x9ffc) {
                    $val -= 0x8140;
                } else {
                    $val -= 0xc140;
                }

                $h = ($val >> 8) * 0xc0;
                $val = ($val & 0xff) + $h;

                $bs->appendNum(13, $val);
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception) {
            return -1;
        }
    }

    /**
     * @return int
     */
    public function encodeModeStructure(): int
    {
        try {
            $bs = new QRCodeBitStream();

            $bs->appendNum(4, 0x03);
            $bs->appendNum(4, ord($this->data[1]) - 1);
            $bs->appendNum(4, ord($this->data[0]) - 1);
            $bs->appendNum(8, ord($this->data[2]));

            $this->bstream = $bs;

            return 0;
        } catch (Exception) {
            return -1;
        }
    }

    /**
     * @param int $version
     * @return float|int
     */
    public function estimateBitStreamSizeOfEntry(int $version): float|int
    {
        $bits = 0;

        if ($version === 0) {
            $version = 1;
        }

        switch ($this->mode) {
            case QRCodeEncodingModeConst::MODE_NUM:
                $bits = QRCodeInput::estimateBitsModeNum($this->size);
                break;
            case QRCodeEncodingModeConst::MODE_AN:
                $bits = QRCodeInput::estimateBitsModeAn($this->size);
                break;
            case QRCodeEncodingModeConst::MODE_8:
                $bits = QRCodeInput::estimateBitsMode8($this->size);
                break;
            case QRCodeEncodingModeConst::MODE_KANJI:
                $bits = QRCodeInput::estimateBitsModeKanji($this->size);
                break;
            case QRCodeEncodingModeConst::MODE_STRUCTURE:
                return QRCodeEncoderConfigConst::STRUCTURE_HEADER_BITS;
            default:
                return 0;
        }

        $l = QRCodeSpecification::lengthIndicator($this->mode, $version);
        $m = 1 << $l;
        $num = (int)(($this->size + $m - 1) / $m);

        $bits += $num * (4 + $l);

        return $bits;
    }

    /**
     * @param int $version
     * @return int
     */
    public function encodeBitStream(int $version): int
    {
        try {
            unset($this->bstream);
            $words = QRCodeSpecification::maximumWords($this->mode, $version);

            if ($this->size > $words) {
                $st1 = new self($this->mode, $words, $this->data);
                $st2 = new self($this->mode, $this->size - $words, array_slice($this->data, $words));

                $st1->encodeBitStream($version);
                $st2->encodeBitStream($version);

                $this->bstream = new QRCodeBitStream();
                $this->bstream->append($st1->bstream);
                $this->bstream->append($st2->bstream);

                unset($st1, $st2);
            } else {
                $ret = 0;

                switch ($this->mode) {
                    case QRCodeEncodingModeConst::MODE_NUM:
                        $ret = $this->encodeModeNum($version);
                        break;
                    case QRCodeEncodingModeConst::MODE_AN:
                        $ret = $this->encodeModeAn($version);
                        break;
                    case QRCodeEncodingModeConst::MODE_8:
                        $ret = $this->encodeMode8($version);
                        break;
                    case QRCodeEncodingModeConst::MODE_KANJI:
                        $ret = $this->encodeModeKanji($version);
                        break;
                    case QRCodeEncodingModeConst::MODE_STRUCTURE:
                        $ret = $this->encodeModeStructure();
                        break;
                    default:
                        break;
                }

                if ($ret < 0) {
                    return -1;
                }
            }

            return $this->bstream->size();
        } catch (Exception) {
            return -1;
        }
    }
}
