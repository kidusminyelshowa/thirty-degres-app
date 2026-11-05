<?php

namespace App\Localisation;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The languages the app's own screens come in (config/languages.php), and
 * which one a request is shown in.
 */
class InterfaceLanguages
{
    public const COOKIE = 'locale';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        /** @var list<string> $codes */
        $codes = config('languages.interface');

        return $codes;
    }

    public static function supports(mixed $code): bool
    {
        return is_string($code) && in_array($code, self::codes(), true);
    }

    /**
     * Each language, named in itself, for the language switch.
     *
     * @return list<array{code: string, native: string}>
     */
    public static function all(): array
    {
        return array_map(fn (string $code) => [
            'code' => $code,
            'native' => (string) config("languages.content.{$code}.native", $code),
        ], self::codes());
    }

    /**
     * The person's own choice first, then what they chose before signing in,
     * then their browser's languages.
     */
    public static function forRequest(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof User && is_string($user->locale) && self::supports($user->locale)) {
            return $user->locale;
        }

        $cookie = $request->cookie(self::COOKIE);

        if (is_string($cookie) && self::supports($cookie)) {
            return $cookie;
        }

        return $request->getPreferredLanguage(self::codes()) ?? self::codes()[0];
    }
}
