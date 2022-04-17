<?php

namespace miyasinarafat\QRCode\Enums;

enum QRCodeSpecificationEnum: int
{
    case VERSION_MAX = 40;
    case WIDTH_MAX = 177;
    case CAP_WIDTH = 0;
    case CAP_WORDS = 1;
    case CAP_REMINDER = 2;
    case CAP_EC = 3;
}
