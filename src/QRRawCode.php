<?php

namespace miyasinarafat\QRCode;

use RuntimeException;

class QRRawCode
{
    public int $version;
    public ?array $datacode = [];
    public array $ecccode = [];
    public mixed $blocks;
    public array $rsblocks = []; //of RSblock
    public int $count;
    public int|float $dataLength;
    public int $eccLength;
    public mixed $b1;

    public function __construct(QRCodeInput $input)
    {
        $spec = [0,0,0,0,0];
        $this->datacode = $input->getByteStream();

        if (is_null($this->datacode)) {
            throw new RuntimeException('null input string');
        }

        QRCodeSpecification::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

        $this->version = $input->getVersion();
        $this->b1 = QRCodeSpecification::rsBlockNum1($spec);
        $this->dataLength = QRCodeSpecification::rsDataLength($spec);
        $this->eccLength = QRCodeSpecification::rsEccLength($spec);
        $this->ecccode = array_fill(0, $this->eccLength, 0);
        $this->blocks = QRCodeSpecification::rsBlockNum($spec);

        if ($this->init($spec) < 0) {
            throw new RuntimeException('block alloc error');
        }

        $this->count = 0;
    }

    /**
     * @param array $spec
     * @return int
     */
    public function init(array $spec): int
    {
        $dl = QRCodeSpecification::rsDataCodes1($spec);
        $el = QRCodeSpecification::rsEccCodes1($spec);
        $rs = QRCodeReedSolomon::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);


        $blockNo = 0;
        $dataPos = 0;
        $eccPos = 0;

        for ($i = 0; $i < QRCodeSpecification::rsBlockNum1($spec); $i++) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new QRCodeReedSolomonBlock($dl, array_slice($this->datacode, $dataPos), $el,  $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        if (QRCodeSpecification::rsBlockNum2($spec) === 0) {
            return 0;
        }

        $dl = QRCodeSpecification::rsDataCodes2($spec);
        $el = QRCodeSpecification::rsEccCodes2($spec);
        $rs = QRCodeReedSolomon::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);

        if ($rs === null) {
            return -1;
        }

        for ($i = 0; $i < QRCodeSpecification::rsBlockNum2($spec); $i++) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new QRCodeReedSolomonBlock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        return 0;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        $ret = 0;

        if ($this->count < $this->dataLength) {
            $row = $this->count % $this->blocks;
            $col = $this->count / $this->blocks;

            if ($col >= $this->rsblocks[0]->dataLength) {
                $row += $this->b1;
            }

            $row = (int)$row;
            $col = (int)$col;

            $ret = $this->rsblocks[$row]->data[$col];
        } elseif ($this->count < $this->dataLength + $this->eccLength) {
            $row = ($this->count - $this->dataLength) % $this->blocks;
            $col = ($this->count - $this->dataLength) / $this->blocks;
            $row = (int)$row;
            $col = (int)$col;

            $ret = $this->rsblocks[$row]->ecc[$col];
        } else {
            return 0;
        }

        $this->count++;

        return $ret;
    }
}
