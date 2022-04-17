<?php

namespace miyasinarafat\QRCode;

class QRCodeReedSolomonItem
{
    /**
     * Bits per symbol
     */
    public int $mm;

    /**
     * Symbols per block (= (1<<mm)-1)
     */
    public int $nn;

    /**
     * log lookup table
     */
    public array $alpha_to = [];

    /**
     * Antilog lookup table
     */
    public array $index_of = [];

    /**
     * Generator polynomial
     */
    public array $genpoly = [];

    /**
     * Number of generator roots = number of parity symbols
     */
    public int $nroots;

    /**
     * First consecutive root, index form
     */
    public int $fcr;

    /**
     * Primitive element, index form
     */
    public int $prim;

    /**
     * prim-th root of 1, index form
     */
    public int $iprim;

    /**
     * Padding bytes in shortened block
     */
    public int $pad;

    public int $gfpoly;

    /**
     * @param int $x
     * @return int
     */
    public function modnn(int $x): int
    {
        while ($x >= $this->nn) {
            $x -= $this->nn;
            $x = ($x >> $this->mm) + ($x & $this->nn);
        }

        return $x;
    }

    /**
     * Common code for intializing a Reed-Solomon control block (char or int symbols)
     * Copyright 2004 Phil Karn, KA9Q
     * May be used under the terms of the GNU Lesser General Public License (LGPL)
     * @param int $symsize
     * @param int $gfpoly
     * @param int $fcr
     * @param int $prim
     * @param int $nroots
     * @param int $pad
     * @return static|null
     */
    public static function init_rs_char(int $symsize, int $gfpoly, int $fcr, int $prim, int $nroots, int $pad): ?self
    {
        // Check parameter ranges
        if ($symsize < 0 || $symsize > 8) {
            return null;
        }

        if ($fcr < 0 || $fcr >= (1 << $symsize)) {
            return null;
        }

        if ($prim <= 0 || $prim >= (1 << $symsize)) {
            return null;
        }

        if ($nroots < 0 || $nroots >= (1 << $symsize)) {
            return null;
        }

        // Can't have more roots than symbol values!
        if ($pad < 0 || $pad >= ((1 << $symsize) - 1 - $nroots)) {
            return null;
        }

        // Too much padding

        $rs = new self();
        $rs->mm = $symsize;
        $rs->nn = (1 << $symsize) - 1;
        $rs->pad = $pad;

        $rs->alpha_to = array_fill(0, $rs->nn + 1, 0);
        $rs->index_of = array_fill(0, $rs->nn + 1, 0);

        // PHP style macro replacement ;)
        $NN = &$rs->nn;
        $A0 = &$NN;

        // Generate Galois field lookup tables
        $rs->index_of[0] = $A0; // log(zero) = -inf
        $rs->alpha_to[$A0] = 0; // alpha**-inf = 0
        $sr = 1;

        for ($i = 0; $i < $rs->nn; $i++) {
            $rs->index_of[$sr] = $i;
            $rs->alpha_to[$i] = $sr;
            $sr <<= 1;
            if ($sr & (1 << $symsize)) {
                $sr ^= $gfpoly;
            }
            $sr &= $rs->nn;
        }

        // field generator polynomial is not primitive!
        if ($sr !== 1) {
            return null;
        }

        // Form RS code generator polynomial from its roots
        $rs->genpoly = array_fill(0, $nroots + 1, 0);

        $rs->fcr = $fcr;
        $rs->prim = $prim;
        $rs->nroots = $nroots;
        $rs->gfpoly = $gfpoly;

        /* Find prim-th root of 1, used in decoding */
        for ($iprim = 1; ($iprim % $prim) !== 0; $iprim += $rs->nn) {
        }; // intentional empty-body loop!

        $rs->iprim = (int)($iprim / $prim);
        $rs->genpoly[0] = 1;

        for ($i = 0,$root = $fcr * $prim; $i < $nroots; $i++, $root += $prim) {
            $rs->genpoly[$i + 1] = 1;

            // Multiply rs->genpoly[] by  @**(root + x)
            for ($j = $i; $j > 0; $j--) {
                if ($rs->genpoly[$j] !== 0) {
                    $rs->genpoly[$j] = $rs->genpoly[$j - 1] ^ $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[$j]] + $root)];
                } else {
                    $rs->genpoly[$j] = $rs->genpoly[$j - 1];
                }
            }

            // rs->genpoly[0] can never be zero
            $rs->genpoly[0] = $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[0]] + $root)];
        }

        // convert rs->genpoly[] to index form for quicker encoding
        for ($i = 0; $i <= $nroots; $i++) {
            $rs->genpoly[$i] = $rs->index_of[$rs->genpoly[$i]];
        }

        return $rs;
    }

    /**
     * @param array $data
     * @param $parity
     * @return void
     */
    public function encode_rs_char(array $data, &$parity): void
    {
        $NN = &$this->nn;
        $ALPHA_TO = &$this->alpha_to;
        $INDEX_OF = &$this->index_of;
        $GENPOLY = &$this->genpoly;
        $NROOTS = &$this->nroots;
        $PAD = &$this->pad;
        $A0 = &$NN;

        $parity = array_fill(0, $NROOTS, 0);

        for ($i = 0; $i < ($NN - $NROOTS - $PAD); $i++) {
            $feedback = $INDEX_OF[$data[$i] ^ $parity[0]];

            if ($feedback !== $A0) {
                // feedback term is non-zero
                // This line is unnecessary when GENPOLY[NROOTS] is unity, as it must
                // always be for the polynomials constructed by init_rs()
                $feedback = $this->modnn($NN - $GENPOLY[$NROOTS] + $feedback);

                for ($j = 1;$j < $NROOTS;$j++) {
                    $parity[$j] ^= $ALPHA_TO[$this->modnn($feedback + $GENPOLY[$NROOTS - $j])];
                }
            }

            // Shift
            array_shift($parity);

            if ($feedback !== $A0) {
                $parity[] = $ALPHA_TO[$this->modnn($feedback + $GENPOLY[0])];
            } else {
                $parity[] = 0;
            }
        }
    }
}
