<?php

namespace App\Enums;

/**
 * Whether a light preview exists for a file. Fonts, documents and other
 * files without one show an icon instead.
 */
enum PreviewStatus: string
{
    case None = 'none';
    case Ready = 'ready';
    case Failed = 'failed';
}
