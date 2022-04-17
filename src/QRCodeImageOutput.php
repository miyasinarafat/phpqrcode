<?php

namespace miyasinarafat\QRCode;

use GdImage;

class QRCodeImageOutput
{
    /**
     * @param array $frame
     * @param mixed $filename
     * @param int $pixelPerPoint
     * @param int $outerFrame
     * @param bool $saveandprint
     * @param int $backColor
     * @param int $foreColor
     * @return void
     */
    public static function png(array $frame, mixed $filename = false, int $pixelPerPoint = 4, int $outerFrame = 4, bool $saveandprint = false, int $backColor = 0xFFFFFF, int $foreColor = 0x000000): void
    {
        $image = self::image($frame, $pixelPerPoint, $outerFrame, $backColor, $foreColor);

        if ($filename === false) {
            Header("Content-type: image/png");
            ImagePng($image);
        } else {
            if ($saveandprint === true) {
                ImagePng($image, $filename);
                header("Content-type: image/png");
                ImagePng($image);
            } else {
                ImagePng($image, $filename);
            }
        }

        ImageDestroy($image);
    }

    /**
     * @param array $frame
     * @param mixed $filename
     * @param int $pixelPerPoint
     * @param int $outerFrame
     * @param int $q
     * @return void
     */
    public static function jpg(array $frame, mixed $filename = false, int $pixelPerPoint = 8, int $outerFrame = 4, int $q = 85): void
    {
        $image = self::image($frame, $pixelPerPoint, $outerFrame);

        if ($filename === false) {
            Header("Content-type: image/jpeg");
            ImageJpeg($image, null, $q);
        } else {
            ImageJpeg($image, $filename, $q);
        }

        ImageDestroy($image);
    }

    /**
     * @param array $frame
     * @param int $pixelPerPoint
     * @param int $outerFrame
     * @param int $backColor
     * @param int $foreColor
     * @return GdImage|bool
     */
    private static function image(array $frame, int $pixelPerPoint = 4, int $outerFrame = 4, int $backColor = 0xFFFFFF, int $foreColor = 0x000000): GdImage|bool
    {
        $h = count($frame);
        $w = strlen($frame[0]);

        $imgW = $w + 2 * $outerFrame;
        $imgH = $h + 2 * $outerFrame;

        $baseImage = ImageCreate($imgW, $imgH);

        // convert a hexadecimal color code into decimal format (red = 255 0 0, green = 0 255 0, blue = 0 0 255)
        $r1 = round((($foreColor & 0xFF0000) >> 16), 5);
        $g1 = round((($foreColor & 0x00FF00) >> 8), 5);
        $b1 = round(($foreColor & 0x0000FF), 5);

        // convert a hexadecimal color code into decimal format (red = 255 0 0, green = 0 255 0, blue = 0 0 255)
        $r2 = round((($backColor & 0xFF0000) >> 16), 5);
        $g2 = round((($backColor & 0x00FF00) >> 8), 5);
        $b2 = round(($backColor & 0x0000FF), 5);



        $col[0] = ImageColorAllocate($baseImage, $r2, $g2, $b2);
        $col[1] = ImageColorAllocate($baseImage, $r1, $g1, $b1);

        imagefill($baseImage, 0, 0, $col[0]);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($frame[$y][$x] === '1') {
                    ImageSetPixel($baseImage, $x + $outerFrame, $y + $outerFrame, $col[1]);
                }
            }
        }

        $targetImage = ImageCreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
        ImageCopyResized($targetImage, $baseImage, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH);
        ImageDestroy($baseImage);

        return $targetImage;
    }
}
