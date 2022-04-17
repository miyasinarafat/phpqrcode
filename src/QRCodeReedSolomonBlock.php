<?php

namespace miyasinarafat\QRCode;

class QRCodeReedSolomonBlock
{
    public int $dataLength;
    public array $data = [];
    public int $eccLength;
    public array $ecc = [];

    public function __construct(int $dl, array $data, int $el, &$ecc, QRCodeReedSolomonItem $rs)
    {
        $rs->encode_rs_char($data, $ecc);

        $this->dataLength = $dl;
        $this->data = $data;
        $this->eccLength = $el;
        $this->ecc = $ecc;
    }
}
