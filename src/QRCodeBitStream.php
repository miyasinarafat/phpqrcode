<?php

namespace miyasinarafat\QRCode;

class QRCodeBitStream
{
    public array $data = [];

    /**
     * @return int
     */
    public function size(): int
    {
        return count($this->data);
    }

    /**
     * @param int $setLength
     * @return void
     */
    public function allocate(int $setLength): void
    {
        $this->data = array_fill(0, $setLength, 0);
    }

    /**
     * @param int $bits
     * @param mixed $num
     * @return static
     */
    public static function newFromNum(int $bits, mixed $num): static
    {
        $bstream = new self();
        $bstream->allocate($bits);

        $mask = 1 << ($bits - 1);
        for ($i = 0; $i < $bits; $i++) {
            if ($num & $mask) {
                $bstream->data[$i] = 1;
            } else {
                $bstream->data[$i] = 0;
            }

            $mask >>= 1;
        }

        return $bstream;
    }

    /**
     * @param int $size
     * @param mixed $data
     * @return static
     */
    public static function newFromBytes(int $size, mixed $data): static
    {
        $bstream = new self();
        $bstream->allocate($size * 8);
        $p = 0;

        for ($i = 0; $i < $size; $i++) {
            $mask = 0x80;
            for ($j = 0; $j < 8; $j++) {
                if ($data[$i] & $mask) {
                    $bstream->data[$p] = 1;
                } else {
                    $bstream->data[$p] = 0;
                }

                $p++;
                $mask >>= 1;
            }
        }

        return $bstream;
    }

    /**
     * @param QRCodeBitStream $arg
     * @return int
     */
    public function append(self $arg): int
    {
        if ($arg->size() === 0) {
            return 0;
        }

        if ($this->size() === 0) {
            $this->data = $arg->data;

            return 0;
        }

        $this->data = array_values(array_merge($this->data, $arg->data));

        return 0;
    }

    /**
     * @param int $bits
     * @param mixed $num
     * @return int
     */
    public function appendNum(int $bits, mixed $num): int
    {
        if ($bits === 0) {
            return 0;
        }

        $b = self::newFromNum($bits, $num);

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    /**
     * @param int $size
     * @param mixed $data
     * @return int
     */
    public function appendBytes(int $size, mixed $data): int
    {
        if ($size === 0) {
            return 0;
        }

        $b = self::newFromBytes($size, $data);

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    /**
     * @return array
     */
    public function toByte(): array
    {
        $size = $this->size();

        if ($size === 0) {
            return [];
        }

        $data = array_fill(0, (int)(($size + 7) / 8), 0);
        $bytes = (int)($size / 8);

        $p = 0;

        for ($i = 0; $i < $bytes; $i++) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v <<= 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$i] = $v;
        }

        if ($size & 7) {
            $v = 0;
            for ($j = 0; $j < ($size & 7); $j++) {
                $v <<= 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$bytes] = $v;
        }

        return $data;
    }
}
