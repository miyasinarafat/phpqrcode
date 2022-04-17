<?php

namespace miyasinarafat\QRCode;

use Exception;
use miyasinarafat\QRCode\Consts\QRCodeEncoderConfigConst;

final class QRCodeTools
{
    /**
     * @param array $frame
     * @return array
     */
    public static function binarize(array $frame): array
    {
        $len = count($frame);

        foreach ($frame as &$frameLine) {
            for ($i = 0; $i < $len; $i++) {
                $frameLine[$i] = (ord($frameLine[$i]) & 1) ? '1' : '0';
            }
        }

        return $frame;
    }

    /**
     * @param string $outfile
     * @param string $error
     * @param bool $logDir
     * @return void
     */
    public static function log(string $outfile, string $error, bool $logDir = false): void
    {
        if (QRCodeEncoderConfigConst::QR_LOG_DIR === false && $logDir === false) {
            return;
        }

        if (empty($error)) {
            return;
        }

        file_put_contents(
            basename($outfile) . '-errors.txt',
            date('Y-m-d H:i:s') . ': ' . $error,
            FILE_APPEND
        );
    }

    /**
     * @param string $markerId
     * @return void
     */
    public static function markTime(string $markerId): void
    {
        [$uses, $sec] = explode(" ", microtime());
        $time = ((float)$uses + (float)$sec);

        if (! isset($GLOBALS['qr_time_bench'])) {
            $GLOBALS['qr_time_bench'] = [];
        }

        $GLOBALS['qr_time_bench'][$markerId] = $time;
    }

    /**
     * @return void
     */
    public static function timeBenchmark(): void
    {
        self::markTime('finish');

        $lastTime = 0;
        $startTime = 0;
        $p = 0;

        echo '<table cellpadding="3" cellspacing="1">
                    <thead><tr style="border-bottom:1px solid silver"><td colspan="2" style="text-align:center">BENCHMARK</td></tr></thead>
                    <tbody>';

        foreach ($GLOBALS['qr_time_bench'] as $markerId => $thisTime) {
            if ($p > 0) {
                echo '<tr><th style="text-align:right">till ' . $markerId . ': </th><td>' . number_format($thisTime - $lastTime, 6) . 's</td></tr>';
            } else {
                $startTime = $thisTime;
            }

            $p++;
            $lastTime = $thisTime;
        }

        echo '</tbody><tfoot>
                <tr style="border-top:2px solid black"><th style="text-align:right">TOTAL: </th><td>' . number_format($lastTime - $startTime, 6) . 's</td></tr>
            </tfoot>
            </table>';
    }

    /**
     * @param string $content
     * @param string $filenamePath
     * @return bool
     */
    public static function save(string $content, string $filenamePath): bool
    {
        try {
            $handle = fopen($filenamePath, 'wb');
            fwrite($handle, $content);
            fclose($handle);

            return true;
        } catch (Exception $e) {
            echo 'Exception reçue : ', $e->getMessage(), "\n";

            return false;
        }
    }
}
