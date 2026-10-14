<?php

namespace App\Guidelines;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The building blocks of a guideline page, and the only shapes the database
 * ever stores. Every piece of text has an English and an Amharic version.
 *
 * - heading:      a section title
 * - text:         paragraphs
 * - colours:      swatches with a name, hex value and note
 * - type_pairing: a Latin and an Ethiopic typeface side by side, with a size scale
 * - logos:        logo files from the library, e.g. bilingual lockups
 * - dos_donts:    "do" and "don't" examples, with or without a picture
 *
 * Keep resources/js/types/guideline.ts in step with this file.
 */
class GuidelineBlocks
{
    public const TYPES = ['heading', 'text', 'colours', 'type_pairing', 'logos', 'dos_donts'];

    public const MAX_BLOCKS = 60;

    public const TEMPLATES = ['blank', 'logo', 'colour', 'type', 'voice'];

    private const SHORT = 300;

    private const LONG = 5000;

    /**
     * Checks what the editor sent and returns it cleaned: known fields only,
     * trimmed text, upper-case hex values.
     *
     * @param  array<mixed>  $input
     * @return list<array<string, mixed>>
     */
    public static function validate(array $input): array
    {
        $rules = [
            'blocks' => ['present', 'array', 'max:'.self::MAX_BLOCKS],
            'blocks.*' => ['array:id,type,text,swatches,latin,ethiopic,scale,note,items'],
            'blocks.*.id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{1,40}$/'],
            'blocks.*.type' => ['required', Rule::in(self::TYPES)],
        ];

        $blocks = is_array($input['blocks'] ?? null) ? $input['blocks'] : [];

        foreach ($blocks as $index => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $prefix = "blocks.{$index}";

            $rules += match ($type) {
                'heading' => self::bilingual("{$prefix}.text", self::SHORT),
                'text' => self::bilingual("{$prefix}.text", self::LONG),
                'colours' => [
                    "{$prefix}.swatches" => ['present', 'array', 'max:24'],
                    ...self::bilingual("{$prefix}.swatches.*.name", 80),
                    "{$prefix}.swatches.*.hex" => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                    ...self::bilingual("{$prefix}.swatches.*.note", self::SHORT),
                ],
                'type_pairing' => [
                    ...self::font("{$prefix}.latin"),
                    ...self::font("{$prefix}.ethiopic"),
                    "{$prefix}.scale" => ['present', 'array', 'max:8'],
                    ...self::bilingual("{$prefix}.scale.*.label", 80),
                    "{$prefix}.scale.*.latin" => ['required', 'integer', 'between:8,160'],
                    "{$prefix}.scale.*.ethiopic" => ['required', 'integer', 'between:8,160'],
                    ...self::bilingual("{$prefix}.note", self::LONG),
                ],
                'logos' => [
                    "{$prefix}.items" => ['present', 'array', 'max:12'],
                    "{$prefix}.items.*.assetId" => ['nullable', 'uuid'],
                    ...self::bilingual("{$prefix}.items.*.caption", self::SHORT),
                    "{$prefix}.items.*.background" => ['required', Rule::in(['light', 'dark'])],
                ],
                'dos_donts' => [
                    "{$prefix}.items" => ['present', 'array', 'max:16'],
                    "{$prefix}.items.*.kind" => ['required', Rule::in(['do', 'dont'])],
                    "{$prefix}.items.*.assetId" => ['nullable', 'uuid'],
                    ...self::bilingual("{$prefix}.items.*.caption", self::SHORT),
                ],
                default => [],
            };
        }

        Validator::make(['blocks' => $blocks], $rules, [
            'blocks.*.swatches.*.hex.regex' => __('Use a hex colour such as #01803B.'),
        ])->validate();

        return array_values(array_map(self::clean(...), $blocks));
    }

    /**
     * Every library file a set of blocks points at.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    public static function assetIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            foreach (self::assetSlots($block) as [$id, $purpose]) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Files the page uses as typefaces. They must be font files.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    public static function fontAssetIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            foreach (self::assetSlots($block) as [$id, $purpose]) {
                if ($purpose === 'font') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * A page's first blocks, so nobody starts from nothing. Amharic is left
     * for the brand to write in its own words.
     *
     * @return array{title_en: string, title_am: string, blocks: list<array<string, mixed>>}
     */
    public static function template(string $name): array
    {
        $blank = ['en' => '', 'am' => ''];
        $text = fn (string $en) => ['en' => $en, 'am' => ''];
        $block = fn (string $type, array $fields) => ['id' => self::newId(), 'type' => $type, ...$fields];

        return match ($name) {
            'logo' => [
                'title_en' => 'Logo',
                'title_am' => '',
                'blocks' => [
                    $block('heading', ['text' => $text('Our logo')]),
                    $block('text', ['text' => $text('Explain what the logo stands for and when to use each version.')]),
                    $block('logos', ['items' => [
                        ['assetId' => null, 'caption' => $text('Primary lockup: Amharic and English'), 'background' => 'light'],
                        ['assetId' => null, 'caption' => $text('On dark backgrounds'), 'background' => 'dark'],
                    ]]),
                    $block('heading', ['text' => $text('Using the logo')]),
                    $block('dos_donts', ['items' => [
                        ['kind' => 'do', 'assetId' => null, 'caption' => $text('Leave clear space around the logo.')],
                        ['kind' => 'dont', 'assetId' => null, 'caption' => $text("Don't stretch, recolour or rotate the logo.")],
                        ['kind' => 'dont', 'assetId' => null, 'caption' => $text("Don't separate the Amharic and English names.")],
                    ]]),
                ],
            ],
            'colour' => [
                'title_en' => 'Colour',
                'title_am' => '',
                'blocks' => [
                    $block('heading', ['text' => $text('Our colours')]),
                    $block('colours', ['swatches' => [
                        ['name' => $text('Primary'), 'hex' => '#01803B', 'note' => $text('Use for the logo and main buttons.')],
                        ['name' => $text('Dark'), 'hex' => '#022018', 'note' => $text('Use for text.')],
                        ['name' => $text('Light'), 'hex' => '#F6F9F7', 'note' => $text('Use for backgrounds.')],
                    ]]),
                ],
            ],
            'type' => [
                'title_en' => 'Typography',
                'title_am' => '',
                'blocks' => [
                    $block('heading', ['text' => $text('Our typefaces')]),
                    $block('type_pairing', [
                        'latin' => ['family' => 'Instrument Sans', 'assetId' => null, 'weight' => 600, 'sample' => 'The quick brown fox jumps over the lazy dog'],
                        'ethiopic' => ['family' => 'Noto Sans Ethiopic', 'assetId' => null, 'weight' => 500, 'sample' => 'ሀ ለ ሐ መ ሠ ረ ሰ ሸ ቀ በ ተ ቸ ነ ኘ አ ከ ወ ዘ የ ደ ጀ ገ ጠ ጨ ፈ ፐ'],
                        'scale' => [
                            ['label' => $text('Headline'), 'latin' => 40, 'ethiopic' => 36],
                            ['label' => $text('Subheading'), 'latin' => 24, 'ethiopic' => 22],
                            ['label' => $text('Body'), 'latin' => 16, 'ethiopic' => 16],
                        ],
                        'note' => $text('Ethiopic runs taller than Latin. Set it slightly smaller in headlines and give it more line height.'),
                    ]),
                ],
            ],
            'voice' => [
                'title_en' => 'Voice',
                'title_am' => '',
                'blocks' => [
                    $block('heading', ['text' => $text('How we sound')]),
                    $block('text', ['text' => $text('Describe the brand in three words, and how it speaks to customers in Amharic and in English.')]),
                    $block('dos_donts', ['items' => [
                        ['kind' => 'do', 'assetId' => null, 'caption' => $blank],
                        ['kind' => 'dont', 'assetId' => null, 'caption' => $blank],
                    ]]),
                ],
            ],
            default => ['title_en' => '', 'title_am' => '', 'blocks' => []],
        };
    }

    public static function newId(): string
    {
        return Str::lower(Str::random(12));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function bilingual(string $key, int $max): array
    {
        return [
            $key => ['required', 'array:en,am'],
            "{$key}.en" => ['present', 'nullable', 'string', "max:{$max}"],
            "{$key}.am" => ['present', 'nullable', 'string', "max:{$max}"],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function font(string $key): array
    {
        return [
            $key => ['required', 'array:family,assetId,weight,sample'],
            "{$key}.family" => ['present', 'nullable', 'string', 'max:80'],
            "{$key}.assetId" => ['nullable', 'uuid'],
            "{$key}.weight" => ['required', 'integer', Rule::in([300, 400, 500, 600, 700, 800])],
            "{$key}.sample" => ['present', 'nullable', 'string', 'max:'.self::SHORT],
        ];
    }

    /**
     * @param  array<mixed>  $block
     * @return array<string, mixed>
     */
    private static function clean(array $block): array
    {
        $id = is_string($block['id'] ?? null) && $block['id'] !== '' ? $block['id'] : self::newId();
        $type = $block['type'];

        return ['id' => $id, 'type' => $type, ...match ($type) {
            'heading', 'text' => ['text' => self::cleanText($block['text'])],
            'colours' => ['swatches' => self::each($block['swatches'], fn (array $swatch) => [
                'name' => self::cleanText($swatch['name']),
                'hex' => strtoupper($swatch['hex']),
                'note' => self::cleanText($swatch['note']),
            ])],
            'type_pairing' => [
                'latin' => self::cleanFont($block['latin']),
                'ethiopic' => self::cleanFont($block['ethiopic']),
                'scale' => self::each($block['scale'], fn (array $row) => [
                    'label' => self::cleanText($row['label']),
                    'latin' => (int) $row['latin'],
                    'ethiopic' => (int) $row['ethiopic'],
                ]),
                'note' => self::cleanText($block['note']),
            ],
            'logos' => ['items' => self::each($block['items'], fn (array $item) => [
                'assetId' => self::cleanId($item['assetId'] ?? null),
                'caption' => self::cleanText($item['caption']),
                'background' => $item['background'],
            ])],
            'dos_donts' => ['items' => self::each($block['items'], fn (array $item) => [
                'kind' => $item['kind'],
                'assetId' => self::cleanId($item['assetId'] ?? null),
                'caption' => self::cleanText($item['caption']),
            ])],
            default => [],
        }];
    }

    /**
     * @param  array<mixed>  $items
     * @param  callable(array<mixed>): array<string, mixed>  $clean
     * @return list<array<string, mixed>>
     */
    private static function each(array $items, callable $clean): array
    {
        return array_values(array_map(fn (mixed $item) => $clean(is_array($item) ? $item : []), $items));
    }

    /**
     * @param  array<mixed>  $text
     * @return array{en: string, am: string}
     */
    private static function cleanText(array $text): array
    {
        return [
            'en' => trim(is_string($text['en'] ?? null) ? $text['en'] : ''),
            'am' => trim(is_string($text['am'] ?? null) ? $text['am'] : ''),
        ];
    }

    /**
     * @param  array<mixed>  $font
     * @return array{family: string, assetId: string|null, weight: int, sample: string}
     */
    private static function cleanFont(array $font): array
    {
        return [
            'family' => trim(is_string($font['family'] ?? null) ? $font['family'] : ''),
            'assetId' => self::cleanId($font['assetId'] ?? null),
            'weight' => is_numeric($font['weight'] ?? null) ? (int) $font['weight'] : 400,
            'sample' => trim(is_string($font['sample'] ?? null) ? $font['sample'] : ''),
        ];
    }

    private static function cleanId(mixed $id): ?string
    {
        return is_string($id) && $id !== '' ? Str::lower($id) : null;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array{0: string, 1: string}>
     */
    private static function assetSlots(array $block): array
    {
        $slots = [];

        $items = match ($block['type'] ?? null) {
            'logos', 'dos_donts' => is_array($block['items'] ?? null) ? $block['items'] : [],
            default => [],
        };

        foreach ($items as $item) {
            if (is_array($item) && is_string($item['assetId'] ?? null)) {
                $slots[] = [$item['assetId'], 'image'];
            }
        }

        if (($block['type'] ?? null) === 'type_pairing') {
            foreach (['latin', 'ethiopic'] as $script) {
                $font = $block[$script] ?? null;

                if (is_array($font) && is_string($font['assetId'] ?? null)) {
                    $slots[] = [$font['assetId'], 'font'];
                }
            }
        }

        return $slots;
    }
}
