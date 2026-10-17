<?php

namespace App\Guidelines;

use App\Models\Brand;

/**
 * The languages guidelines can be written in (config/languages.php).
 */
class Languages
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        /** @var array<string, mixed> $content */
        $content = config('languages.content');

        return array_keys($content);
    }

    /**
     * Every supported language, for the brand's language picker.
     *
     * @return list<array{code: string, name: string, native: string, script: string, dir: string}>
     */
    public static function all(): array
    {
        return array_map(self::describe(...), self::codes());
    }

    /**
     * The brand's languages in its order, leaving out any no longer offered.
     *
     * @return list<array{code: string, name: string, native: string, script: string, dir: string}>
     */
    public static function forBrand(Brand $brand): array
    {
        $codes = array_values(array_intersect($brand->guideline_languages ?: self::defaults(), self::codes()));

        return array_map(self::describe(...), $codes !== [] ? $codes : self::defaults());
    }

    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        /** @var list<string> $defaults */
        $defaults = config('languages.default');

        return $defaults;
    }

    /**
     * @return array{code: string, name: string, native: string, script: string, dir: string}
     */
    private static function describe(string $code): array
    {
        /** @var array{name: string, native: string, script: string, dir: string} $language */
        $language = config("languages.content.{$code}");

        return ['code' => $code, ...$language];
    }
}
