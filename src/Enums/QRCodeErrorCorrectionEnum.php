<?php

namespace miyasinarafat\QRCode\Enums;

enum QRCodeErrorCorrectionEnum: int
{
    case LEVEL_L = 0;
    case LEVEL_M = 1;
    case LEVEL_Q = 2;
    case LEVEL_H = 3;
}
