<?php

namespace miyasinarafat\QRCode;

class QRCodeReedSolomon
{
    public static array $items = [];

    public static function init_rs(int $symsize, int $gfpoly, int $fcr, int $prim, int $nroots, int $pad)
    {
        foreach (self::$items as $rs) {
            if ($rs->pad !== $pad) {
                continue;
            }
            if ($rs->nroots !== $nroots) {
                continue;
            }
            if ($rs->mm !== $symsize) {
                continue;
            }
            if ($rs->gfpoly !== $gfpoly) {
                continue;
            }
            if ($rs->fcr !== $fcr) {
                continue;
            }
            if ($rs->prim !== $prim) {
                continue;
            }

            return $rs;
        }

        $rs = QRCodeReedSolomonItem::init_rs_char($symsize, $gfpoly, $fcr, $prim, $nroots, $pad);
        array_unshift(self::$items, $rs);

        return $rs;
    }
}
