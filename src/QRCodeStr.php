<?php

namespace miyasinarafat\QRCode;

final class QRCodeStr
{
    /**
     * @param array $srcTab
     * @param int $x
     * @param int $y
     * @param string $repl
     * @param int|null $replLength
     * @return void
     */
    public static function set(array &$srcTab, int $x, int $y, string $repl, ?int $replLength = null): void
    {
        $srcTab[$y] = substr_replace(
            $srcTab[$y],
            $replLength
                ? substr($repl, 0, $replLength)
                : $repl,
            $x,
            $replLength ?? strlen($repl)
        );
    }
}
