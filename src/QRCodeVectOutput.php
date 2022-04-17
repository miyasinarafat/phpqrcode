<?php

namespace miyasinarafat\QRCode;

class QRCodeVectOutput
{
    public static function eps(array $frame, mixed $filename = false, int $pixelPerPoint = 4, int $outerFrame = 4, bool $saveandprint = false, int $backColor = 0xFFFFFF, int $foreColor = 0x000000, bool $cmyk = false)
    {
        $vect = self::vectEPS($frame, $pixelPerPoint, $outerFrame, $backColor, $foreColor, $cmyk);

        if ($filename === false) {
            header("Content-Type: application/postscript");
            header('Content-Disposition: filename="qrcode.eps"');
            echo $vect;
        } else {
            if ($saveandprint === true) {
                QRCodeTools::save($vect, $filename);
                header("Content-Type: application/postscript");
                header('Content-Disposition: filename="qrcode.eps"');
                echo $vect;
            } else {
                QRCodeTools::save($vect, $filename);
            }
        }
    }

    /**
     * @param array $frame
     * @param int $pixelPerPoint
     * @param int $outerFrame
     * @param int $backColor
     * @param int $foreColor
     * @param bool $cmyk
     * @return string
     */
    private static function vectEPS(array $frame, int $pixelPerPoint = 4, int $outerFrame = 4, int $backColor = 0xFFFFFF, int $foreColor = 0x000000, bool $cmyk = false): string
    {
        $h = count($frame);
        $w = strlen($frame[0]);

        $imgW = $w + 2 * $outerFrame;
        $imgH = $h + 2 * $outerFrame;

        if ($cmyk) {
            // convert color value into decimal eps format
            $c = round((($foreColor & 0xFF000000) >> 16) / 255, 5);
            $m = round((($foreColor & 0x00FF0000) >> 16) / 255, 5);
            $y = round((($foreColor & 0x0000FF00) >> 8) / 255, 5);
            $k = round(($foreColor & 0x000000FF) / 255, 5);
            $foreColor_string = $c.' '.$m.' '.$y.' '.$k.' setcmykcolor'."\n";

            // convert color value into decimal eps format
            $c = round((($backColor & 0xFF000000) >> 16) / 255, 5);
            $m = round((($backColor & 0x00FF0000) >> 16) / 255, 5);
            $y = round((($backColor & 0x0000FF00) >> 8) / 255, 5);
            $k = round(($backColor & 0x000000FF) / 255, 5);
            $backColor_string = $c.' '.$m.' '.$y.' '.$k.' setcmykcolor'."\n";
        } else {
            // convert a hexadecimal color code into decimal eps format (green = 0 1 0, blue = 0 0 1, ...)
            $r = round((($foreColor & 0xFF0000) >> 16) / 255, 5);
            $b = round((($foreColor & 0x00FF00) >> 8) / 255, 5);
            $g = round(($foreColor & 0x0000FF) / 255, 5);
            $foreColor_string = $r.' '.$b.' '.$g.' setrgbcolor'."\n";

            // convert a hexadecimal color code into decimal eps format (green = 0 1 0, blue = 0 0 1, ...)
            $r = round((($backColor & 0xFF0000) >> 16) / 255, 5);
            $b = round((($backColor & 0x00FF00) >> 8) / 255, 5);
            $g = round(($backColor & 0x0000FF) / 255, 5);
            $backColor_string = $r.' '.$b.' '.$g.' setrgbcolor'."\n";
        }

        $output =
            '%!PS-Adobe EPSF-3.0'."\n".
            '%%Creator: PHPQrcodeLib'."\n".
            '%%Title: QRcode'."\n".
            '%%CreationDate: '.date('Y-m-d')."\n".
            '%%DocumentData: Clean7Bit'."\n".
            '%%LanguageLevel: 2'."\n".
            '%%Pages: 1'."\n".
            '%%BoundingBox: 0 0 '.$imgW * $pixelPerPoint.' '.$imgH * $pixelPerPoint."\n";

        // set the scale
        $output .= $pixelPerPoint.' '.$pixelPerPoint.' scale'."\n";

        // position the center of the coordinate system
        $output .= $outerFrame.' '.$outerFrame.' translate'."\n";

        // redefine the 'rectfill' operator to shorten the syntax
        $output .= '/F { rectfill } def'."\n";

        // set the symbol color
        $output .= $backColor_string;
        $output .= '-'.$outerFrame.' -'.$outerFrame.' '.($w + 2 * $outerFrame).' '.($h + 2 * $outerFrame).' F'."\n";

        // set the symbol color
        $output .= $foreColor_string;

        // Convert the matrix into pixels
        for ($i = 0; $i < $h; $i++) {
            for ($j = 0; $j < $w; $j++) {
                if ($frame[$i][$j] == '1') {
                    $y = $h - 1 - $i;
                    $x = $j;
                    $output .= $x.' '.$y.' 1 1 F'."\n";
                }
            }
        }

        $output .= '%%EOF';

        return $output;
    }

    /**
     * @param $frame
     * @param mixed $filename
     * @param int $pixelPerPoint
     * @param int $outerFrame
     * @param bool $saveandprint
     * @param int $backColor
     * @param int $foreColor
     * @return void
     */
    public static function svg($frame, mixed $filename = false, int $pixelPerPoint = 4, int $outerFrame = 4, bool $saveandprint = false, int $backColor = 0xFFFFFF, int $foreColor = 0x000000): void
    {
        $vect = self::vectSVG($frame, $pixelPerPoint, $outerFrame, $backColor, $foreColor);

        if ($filename === false) {
            header("Content-Type: image/svg+xml");
            echo $vect;
        } else {
            QRCodeTools::save($vect, $filename);

            if ($saveandprint === true) {
                header("Content-Type: image/svg+xml");
                echo $vect;
            }
        }
    }

    /**
     * @param array $frame
     * @param int $pixelPerPoint
     * @param int $outerFrame
     * @param int $backColor
     * @param int $foreColor
     * @return string
     */
    private static function vectSVG(array $frame, int $pixelPerPoint = 4, int $outerFrame = 4, int $backColor = 0xFFFFFF, int $foreColor = 0x000000): string
    {
        $h = count($frame);
        $w = strlen($frame[0]);

        $imgW = $w + 2 * $outerFrame;
        $imgH = $h + 2 * $outerFrame;

        $output =
            '<?xml version="1.0" encoding="utf-8"?>'."\n".
            '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" xmlns:xlink="http://www.w3.org/1999/xlink" width="'.$imgW * $pixelPerPoint.'" height="'.$imgH * $pixelPerPoint.'" viewBox="0 0 '.$imgW * $pixelPerPoint.' '.$imgH * $pixelPerPoint.'">'."\n".
            '<desc></desc>'."\n";

        if (! empty($backColor)) {
            $backgroundcolor = str_pad(dechex($backColor), 6, "0", STR_PAD_LEFT);
            $output .= '<rect width="'.$imgW * $pixelPerPoint.'" height="'.$imgH * $pixelPerPoint.'" fill="#'.$backgroundcolor.'" cx="0" cy="0" />'."\n";
        }

        $output .=
            '<defs>'."\n".
            '<rect id="p" width="'.$pixelPerPoint.'" height="'.$pixelPerPoint.'" />'."\n".
            '</defs>'."\n".
            '<g fill="#'.str_pad(dechex($foreColor), 6, "0", STR_PAD_LEFT).'">'."\n";


        // Convert the matrix into pixels
        for ($i = 0; $i < $h; $i++) {
            for ($j = 0; $j < $w; $j++) {
                if ($frame[$i][$j] === '1') {
                    $y = ($i + $outerFrame) * $pixelPerPoint;
                    $x = ($j + $outerFrame) * $pixelPerPoint;
                    $output .= '<use x="'.$x.'" y="'.$y.'" xlink:href="#p" />'."\n";
                }
            }
        }
        $output .=
            '</g>'."\n".
            '</svg>';

        return $output;
    }
}
