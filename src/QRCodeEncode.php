<?php

namespace miyasinarafat\QRCode;

use Exception;
use miyasinarafat\QRCode\Consts\QRCodeEncoderConfigConst;
use miyasinarafat\QRCode\Consts\QRCodeEncodingModeConst;
use miyasinarafat\QRCode\Enums\QRCodeErrorCorrectionEnum;

class QRCodeEncode
{
    public bool $casesensitive = true;
    public bool $eightbit = false;

    public int $version = 0;
    public int $size = 3;
    public int $margin = 4;
    public int $backColor = 0xFFFFFF;
    public int $foreColor = 0x000000;

    public int $structured = 0; // not supported yet

    public int $level;
    public int $hint = QRCodeEncodingModeConst::MODE_8;
    public bool $cmyk;

    /**
     * @param mixed $level
     * @param int $size
     * @param int $margin
     * @param int $backColor
     * @param int $foreColor
     * @param bool $cmyk
     * @return static
     */
    public static function factory(mixed $level, int $size = 3, int $margin = 4, int $backColor = 0xFFFFFF, int $foreColor = 0x000000, bool $cmyk = false): static
    {
        $level = $level ?? QRCodeErrorCorrectionEnum::LEVEL_L->value;

        $enc = new self();
        $enc->size = $size;
        $enc->margin = $margin;
        $enc->foreColor = $foreColor;
        $enc->backColor = $backColor;
        $enc->cmyk = $cmyk;

        $enc->level = (int) match ($level . '') {
            '0', '1', '2', '3' => $level,
            'l', 'L' => QRCodeErrorCorrectionEnum::LEVEL_L->value,
            'm', 'M' => QRCodeErrorCorrectionEnum::LEVEL_M->value,
            'q', 'Q' => QRCodeErrorCorrectionEnum::LEVEL_Q->value,
            'h', 'H' => QRCodeErrorCorrectionEnum::LEVEL_H->value,
        };

        return $enc;
    }

    /**
     * @param string $intext
     * @param mixed $outfile
     * @return mixed
     * @throws Exception
     */
    public function encodeRAW(string $intext, mixed $outfile = false): mixed
    {
        $code = new QRCode();

        if ($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }

        return $code->data;
    }

    /**
     * @param string $intext
     * @param mixed $outfile
     * @return array|null
     * @throws Exception
     */
    public function encode(string $intext, mixed $outfile = false): ?array
    {
        $code = new QRCode();

        if ($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }

        QRCodeTools::markTime('after_encode');

        if ($outfile !== false) {
            file_put_contents((string)$outfile, implode("\n", QRCodeTools::binarize($code->data)));
            return null;
        }

        return QRCodeTools::binarize($code->data);
    }

    /**
     * @param string $intext
     * @param mixed $outfile
     * @param bool $saveandprint
     * @return void
     */
    public function encodePNG(string $intext, mixed $outfile = false, bool $saveandprint = false): void
    {
        try {
            ob_start();
            $tab = $this->encode($intext);
            $err = ob_get_contents();

            if ($err !== '') {
                QRCodeTools::log($outfile, $err);
            }

            $maxSize = (int)(QRCodeEncoderConfigConst::QR_PNG_MAXIMUM_SIZE / (count($tab) + 2 * $this->margin));

            QRCodeImageOutput::png($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin, $saveandprint, $this->backColor, $this->foreColor);
        } catch (Exception $e) {
            QRCodeTools::log($outfile, $e->getMessage());
        }

        ob_end_clean();
    }


    /**
     * @param string $intext
     * @param mixed $outfile
     * @param bool $saveandprint
     * @return void
     */
    public function encodeEPS(string $intext, mixed $outfile = false, bool $saveandprint = false): void
    {
        try {
            ob_start();
            $tab = $this->encode($intext);
            $err = ob_get_contents();

            if ($err !== '') {
                QRCodeTools::log($outfile, $err);
            }

            $maxSize = (int)(QRCodeEncoderConfigConst::QR_PNG_MAXIMUM_SIZE / (count($tab) + 2 * $this->margin));

            QRCodeVectOutput::eps($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin, $saveandprint, $this->backColor, $this->foreColor, $this->cmyk);
        } catch (Exception $e) {
            QRCodeTools::log($outfile, $e->getMessage());
        }

        ob_end_clean();
    }

    /**
     * @param string $intext
     * @param mixed $outfile
     * @param bool $saveandprint
     * @return void
     */
    public function encodeSVG(string $intext, mixed $outfile = false, bool $saveandprint = false): void
    {
        try {
            ob_start();
            $tab = $this->encode($intext);
            $err = ob_get_contents();

            if ($err !== '') {
                QRCodeTools::log($outfile, $err);
            }

            $maxSize = (int)(QRCodeEncoderConfigConst::QR_PNG_MAXIMUM_SIZE / (count($tab) + 2 * $this->margin));

            QRCodeVectOutput::svg($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin, $saveandprint, $this->backColor, $this->foreColor);
        } catch (Exception $e) {
            QRCodeTools::log($outfile, $e->getMessage());
        }

        ob_end_clean();
    }
}
