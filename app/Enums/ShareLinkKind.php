<?php

namespace App\Enums;

/**
 * What a share link opens: a brand's published guidelines, or a kit of
 * chosen approved files.
 */
enum ShareLinkKind: string
{
    case Guidelines = 'guidelines';
    case Files = 'files';
}
