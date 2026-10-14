<?php

namespace App\Enums;

/**
 * What a file in the library is, for filtering. Guessed from the file's
 * extension on upload; the uploader can change it.
 */
enum AssetKind: string
{
    case Logo = 'logo';
    case Font = 'font';
    case Photo = 'photo';
    case Document = 'document';
    case Other = 'other';

    public static function guess(string $extension): self
    {
        return match (strtolower($extension)) {
            'svg', 'eps', 'ai', 'png' => self::Logo,
            'ttf', 'otf', 'woff', 'woff2' => self::Font,
            'jpg', 'jpeg', 'webp', 'heic', 'tif', 'tiff', 'gif' => self::Photo,
            'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'indd' => self::Document,
            default => self::Other,
        };
    }
}
