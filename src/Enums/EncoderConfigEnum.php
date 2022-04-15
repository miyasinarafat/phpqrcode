<?php

namespace miyasinarafat\QRCode\Enums;

class EncoderConfigEnum
{
    public const QR_CACHEABLE = false; // use cache - more disk reads but less CPU power, masks and format templates are stored there
    public const QR_CACHE_DIR = false; // used when QR_CACHEABLE === true
    public const QR_LOG_DIR = false; // default error logs dir
    public const QR_FIND_BEST_MASK = true; // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
    public const QR_FIND_FROM_RANDOM = 2; // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
    public const QR_DEFAULT_MASK = 2; // when QR_FIND_BEST_MASK === false
    public const QR_PNG_MAXIMUM_SIZE = 1024; // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
}
