<?php

namespace miyasinarafat\QRCode;

use Exception;
use miyasinarafat\QRCode\Consts\QRCodeEncoderConfigConst;
use miyasinarafat\QRCode\Consts\QRCodeEncodingModeConst;
use miyasinarafat\QRCode\Enums\QRCodeErrorCorrectionEnum;
use miyasinarafat\QRCode\Enums\QRCodeSpecificationEnum;
use RuntimeException;

class QRCode
{
    public int $version;
    public int $width;
    public mixed $data;

    /**
     * @param QRCodeInput $input
     * @param int $mask
     * @return static
     * @throws Exception
     */
    public function encodeMask(QRCodeInput $input, int $mask): static
    {
        if ($input->getVersion() < 0 || $input->getVersion() > QRCodeSpecificationEnum::VERSION_MAX->value) {
            throw new RuntimeException('wrong version');
        }

        if ($input->getErrorCorrectionLevel() > QRCodeErrorCorrectionEnum::LEVEL_H->value) {
            throw new RuntimeException('wrong level');
        }

        $raw = new QRRawCode($input);

        QRCodeTools::markTime('after_raw');

        $version = $raw->version;
        $width = QRCodeSpecification::getWidth($version);
        $frame = QRCodeSpecification::newFrame($version);

        $filler = new QRCodeFrameFiller($width, $frame);

        // interleaved data and ecc codes
        for ($i = 0; $i < $raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;

            for ($j = 0; $j < 8; $j++) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (int)(($bit & $code) !== 0));
                $bit >>= 1;
            }
        }

        QRCodeTools::markTime('after_filler');

        unset($raw);

        // remainder bits
        $j = QRCodeSpecification::getRemainder($version);
        for ($i = 0; $i < $j; $i++) {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);


        // masking
        $maskObj = new QRCodeMask();
        if ($mask < 0) {
            if (QRCodeEncoderConfigConst::QR_FIND_BEST_MASK) {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            } else {
                $masked = $maskObj->makeMask($width, $frame, (QRCodeEncoderConfigConst::QR_DEFAULT_MASK % 8), $input->getErrorCorrectionLevel());
            }
        } else {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }

        QRCodeTools::markTime('after_mask');

        $this->version = $version;
        $this->width = $width;
        $this->data = $masked;

        return $this;
    }

    /**
     * @param QRCodeInput $input
     * @return $this
     * @throws Exception
     */
    public function encodeInput(QRCodeInput $input): static
    {
        return $this->encodeMask($input, -1);
    }

    /**
     * @param string $string
     * @param int $version
     * @param int $level
     * @return $this|null
     * @throws Exception
     */
    public function encodeString8bit(string $string, int $version, int $level): ?static
    {
        $input = new QRCodeInput($version, $level);

        $ret = $input->append($input, QRCodeEncodingModeConst::MODE_8, str_split($string));
        if ($ret < 0) {
            unset($input);

            return null;
        }

        return $this->encodeInput($input);
    }

    /**
     * @param string $string
     * @param int $version
     * @param int $level
     * @param int $hint
     * @param bool $casesensitive
     * @return $this|null
     * @throws Exception
     */
    public function encodeString(string $string, int $version, int $level, int $hint, bool $casesensitive): ?static
    {
        if ($hint !== QRCodeEncodingModeConst::MODE_8 && $hint !== QRCodeEncodingModeConst::MODE_KANJI) {
            throw new RuntimeException('bad hint');
        }

        $input = new QRCodeInput($version, $level);

        $ret = QRCodeInputSplit::splitStringToQRCodeInput($string, $input, $hint, $casesensitive);
        if ($ret < 0) {
            return null;
        }

        return $this->encodeInput($input);
    }

    /**
     * @param string $text
     * @param mixed $outfile
     * @param mixed $level
     * @param int $size
     * @param int $margin
     * @param bool $saveandprint
     * @param int $backColor
     * @param int $foreColor
     * @return void
     */
    public static function png(string $text, mixed $outfile = false, mixed $level = null, int $size = 3, int $margin = 4, bool $saveandprint = false, int $backColor = 0xFFFFFF, int $foreColor = 0x000000): void
    {
        $level = $level ?? QRCodeErrorCorrectionEnum::LEVEL_L->value;
        $enc = QRCodeEncode::factory($level, $size, $margin, $backColor, $foreColor);

        $enc->encodePNG($text, $outfile, $saveandprint);
    }

    /**
     * @param string $text
     * @param mixed $outfile
     * @param mixed $level
     * @param int $size
     * @param int $margin
     * @return array|null
     * @throws Exception
     */
    public static function text(string $text, mixed $outfile = false, mixed $level = null, int $size = 3, int $margin = 4): ?array
    {
        $level = $level ?? QRCodeErrorCorrectionEnum::LEVEL_L->value;

        return QRCodeEncode::factory($level, $size, $margin)->encode($text, $outfile);
    }

    /**
     * @param string $text
     * @param mixed $outfile
     * @param mixed $level
     * @param int $size
     * @param int $margin
     * @param bool $saveandprint
     * @param int $backColor
     * @param int $foreColor
     * @param bool $cmyk
     * @return void
     */
    public static function eps(string $text, mixed $outfile = false, mixed $level = null, int $size = 3, int $margin = 4, bool $saveandprint = false, int $backColor = 0xFFFFFF, int $foreColor = 0x000000, bool $cmyk = false): void
    {
        $level = $level ?? QRCodeErrorCorrectionEnum::LEVEL_L->value;
        $enc = QRCodeEncode::factory($level, $size, $margin, $backColor, $foreColor, $cmyk);

        $enc->encodeEPS($text, $outfile, $saveandprint);
    }

    /**
     * @param string $text
     * @param mixed $outfile
     * @param mixed $level
     * @param int $size
     * @param int $margin
     * @param bool $saveandprint
     * @param int $backColor
     * @param int $foreColor
     * @return void
     */
    public static function svg(string $text, mixed $outfile = false, mixed $level = null, int $size = 3, int $margin = 4, bool $saveandprint = false, int $backColor = 0xFFFFFF, int $foreColor = 0x000000): void
    {
        $level = $level ?? QRCodeErrorCorrectionEnum::LEVEL_L->value;
        $enc = QRCodeEncode::factory($level, $size, $margin, $backColor, $foreColor);

        $enc->encodeSVG($text, $outfile, $saveandprint);
    }

    /**
     * @param string $text
     * @param mixed $outfile
     * @param mixed $level
     * @param int $size
     * @param int $margin
     * @return mixed
     * @throws Exception
     */
    public static function raw(string $text, mixed $outfile = false, mixed $level = null, int $size = 3, int $margin = 4): mixed
    {
        $level = $level ?? QRCodeErrorCorrectionEnum::LEVEL_L->value;

        return QRCodeEncode::factory($level, $size, $margin)->encodeRAW($text, $outfile);
    }
}
