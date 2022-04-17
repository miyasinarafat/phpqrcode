<?php

namespace miyasinarafat\QRCode;

class QRCodeFrameFiller
{
    public int $width;
    public array $frame;
    public int $x;
    public int $y;
    public int $dir;
    public int $bit;

    public function __construct(int $width, array &$frame)
    {
        $this->width = $width;
        $this->frame = $frame;
        $this->x = $width - 1;
        $this->y = $width - 1;
        $this->dir = -1;
        $this->bit = -1;
    }

    /**
     * @param array $at
     * @param int $val
     * @return void
     */
    public function setFrameAt(array $at, int $val): void
    {
        $this->frame[$at['y']][$at['x']] = chr($val);
    }

    /**
     * @param array $at
     * @return int
     */
    public function getFrameAt(array $at): int
    {
        return ord($this->frame[$at['y']][$at['x']]);
    }

    /**
     * @return array|null
     */
    public function next(): ?array
    {
        do {
            if ($this->bit === -1) {
                $this->bit = 0;

                return ['x' => $this->x, 'y' => $this->y];
            }

            $x = $this->x;
            $y = $this->y;
            $w = $this->width;

            if ($this->bit === 0) {
                $x--;
                $this->bit++;
            } else {
                $x++;
                $y += $this->dir;
                $this->bit--;
            }

            if ($this->dir < 0) {
                if ($y < 0) {
                    $y = 0;
                    $x -= 2;
                    $this->dir = 1;

                    if ($x === 6) {
                        $x--;
                        $y = 9;
                    }
                }
            } elseif ($y === $w) {
                $y = $w - 1;
                $x -= 2;
                $this->dir = -1;

                if ($x === 6) {
                    $x--;
                    $y -= 8;
                }
            }

            if ($x < 0 || $y < 0) {
                return null;
            }

            $this->x = $x;
            $this->y = $y;

        } while (ord($this->frame[$y][$x]) & 0x80);

        return ['x' => $x, 'y' => $y];
    }
}
