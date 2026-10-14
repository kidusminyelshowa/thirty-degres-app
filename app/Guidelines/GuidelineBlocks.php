<?php

namespace App\Guidelines;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The building blocks of a guideline page, and the only shapes the database
 * ever stores. Every piece of text is a map from language code to text, e.g.
 * {"am": "አርማ", "en": "Logo", "om": "Mallattoo"}, in any of the languages in
 * config/languages.php. Languages not written yet are left out.
 *
 * - heading:      a section title
 * - text:         paragraphs
 * - colours:      swatches with a name, hex value and note
 * - type_pairing: typefaces for each script (Ethiopic, Latin, Arabic) side by side, with a size scale
 * - logos:        logo files from the library, e.g. lockups in several languages
 * - dos_donts:    "do" and "don't" examples, with or without a picture
 *
 * Keep resources/js/types/guideline.ts in step with this file.
 */
class GuidelineBlocks
{
    public const TYPES = ['heading', 'text', 'colours', 'type_pairing', 'logos', 'dos_donts'];

    public const SCRIPTS = ['ethiopic', 'latin', 'arabic'];

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
            'blocks.*' => ['array:id,type,text,swatches,faces,scale,note,items'],
            'blocks.*.id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{1,40}$/'],
            'blocks.*.type' => ['required', Rule::in(self::TYPES)],
        ];

        $blocks = is_array($input['blocks'] ?? null) ? $input['blocks'] : [];

        foreach ($blocks as $index => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $prefix = "blocks.{$index}";

            $rules += match ($type) {
                'heading' => self::text("{$prefix}.text", self::SHORT),
                'text' => self::text("{$prefix}.text", self::LONG),
                'colours' => [
                    "{$prefix}.swatches" => ['present', 'array', 'max:24'],
                    "{$prefix}.swatches.*" => ['array:name,hex,note'],
                    ...self::text("{$prefix}.swatches.*.name", 80),
                    "{$prefix}.swatches.*.hex" => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                    ...self::text("{$prefix}.swatches.*.note", self::SHORT),
                ],
                'type_pairing' => [
                    "{$prefix}.faces" => ['required', 'array', 'min:1', 'max:4'],
                    "{$prefix}.faces.*" => ['array:script,family,assetId,weight,sample'],
                    "{$prefix}.faces.*.script" => ['required', Rule::in(self::SCRIPTS)],
                    "{$prefix}.faces.*.family" => ['present', 'nullable', 'string', 'max:80'],
                    "{$prefix}.faces.*.assetId" => ['nullable', 'uuid'],
                    "{$prefix}.faces.*.weight" => ['required', 'integer', Rule::in([300, 400, 500, 600, 700, 800])],
                    "{$prefix}.faces.*.sample" => ['present', 'nullable', 'string', 'max:'.self::SHORT],
                    "{$prefix}.scale" => ['present', 'array', 'max:8'],
                    "{$prefix}.scale.*" => ['array:label,sizes'],
                    ...self::text("{$prefix}.scale.*.label", 80),
                    "{$prefix}.scale.*.sizes" => ['present', 'array:'.implode(',', self::SCRIPTS)],
                    "{$prefix}.scale.*.sizes.*" => ['integer', 'between:8,160'],
                    ...self::text("{$prefix}.note", self::LONG),
                ],
                'logos' => [
                    "{$prefix}.items" => ['present', 'array', 'max:12'],
                    "{$prefix}.items.*" => ['array:assetId,caption,background'],
                    "{$prefix}.items.*.assetId" => ['nullable', 'uuid'],
                    ...self::text("{$prefix}.items.*.caption", self::SHORT),
                    "{$prefix}.items.*.background" => ['required', Rule::in(['light', 'dark'])],
                ],
                'dos_donts' => [
                    "{$prefix}.items" => ['present', 'array', 'max:16'],
                    "{$prefix}.items.*" => ['array:kind,assetId,caption'],
                    "{$prefix}.items.*.kind" => ['required', Rule::in(['do', 'dont'])],
                    "{$prefix}.items.*.assetId" => ['nullable', 'uuid'],
                    ...self::text("{$prefix}.items.*.caption", self::SHORT),
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
     * Rules for a page title, in each language.
     *
     * @return array<string, list<mixed>>
     */
    public static function titleRules(): array
    {
        return self::text('title', 120);
    }

    /**
     * Text in each language, trimmed, with languages left blank dropped.
     *
     * @param  mixed  $text
     * @return array<string, string>
     */
    public static function cleanText(mixed $text): array
    {
        $clean = [];

        if (! is_array($text)) {
            return $clean;
        }

        foreach (Languages::codes() as $code) {
            $value = $text[$code] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $clean[$code] = trim($value);
            }
        }

        return $clean;
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
            foreach (self::assetSlots($block) as [$id]) {
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
     * A page's first blocks, so nobody starts from nothing. Only the English
     * prompts are filled in; the brand writes its other languages in its
     * own words.
     *
     * @return array{title: array<string, string>, blocks: list<array<string, mixed>>}
     */
    public static function template(string $name): array
    {
        $text = fn (string $en) => ['en' => $en];
        $block = fn (string $type, array $fields) => ['id' => self::newId(), 'type' => $type, ...$fields];

        return match ($name) {
            'logo' => [
                'title' => $text('Logo'),
                'blocks' => [
                    $block('heading', ['text' => $text('Our logo')]),
                    $block('text', ['text' => $text('Explain what the logo stands for and when to use each version.')]),
                    $block('logos', ['items' => [
                        ['assetId' => null, 'caption' => $text('Primary lockup with the name in Ge’ez and Latin script'), 'background' => 'light'],
                        ['assetId' => null, 'caption' => $text('On dark backgrounds'), 'background' => 'dark'],
                    ]]),
                    $block('heading', ['text' => $text('Using the logo')]),
                    $block('dos_donts', ['items' => [
                        ['kind' => 'do', 'assetId' => null, 'caption' => $text('Leave clear space around the logo.')],
                        ['kind' => 'dont', 'assetId' => null, 'caption' => $text("Don't stretch, recolour or rotate the logo.")],
                        ['kind' => 'dont', 'assetId' => null, 'caption' => $text("Don't separate the names in different scripts.")],
                    ]]),
                ],
            ],
            'colour' => [
                'title' => $text('Colour'),
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
                'title' => $text('Typography'),
                'blocks' => [
                    $block('heading', ['text' => $text('Our typefaces')]),
                    $block('type_pairing', [
                        'faces' => [
                            ['script' => 'ethiopic', 'family' => 'Noto Sans Ethiopic', 'assetId' => null, 'weight' => 500, 'sample' => 'ሀ ለ ሐ መ ሠ ረ ሰ ሸ ቀ በ ተ ቸ ነ ኘ አ ከ ወ ዘ የ ደ ጀ ገ ጠ ጨ ፈ ፐ'],
                            ['script' => 'latin', 'family' => 'Instrument Sans', 'assetId' => null, 'weight' => 600, 'sample' => 'The quick brown fox jumps over the lazy dog'],
                        ],
                        'scale' => [
                            ['label' => $text('Headline'), 'sizes' => ['ethiopic' => 36, 'latin' => 40]],
                            ['label' => $text('Subheading'), 'sizes' => ['ethiopic' => 22, 'latin' => 24]],
                            ['label' => $text('Body'), 'sizes' => ['ethiopic' => 16, 'latin' => 16]],
                        ],
                        'note' => $text('Ethiopic runs taller than Latin. Set it slightly smaller in headlines and give it more line height.'),
                    ]),
                ],
            ],
            'voice' => [
                'title' => $text('Voice'),
                'blocks' => [
                    $block('heading', ['text' => $text('How we sound')]),
                    $block('text', ['text' => $text('Describe the brand in three words, and how it speaks to customers in each of its languages.')]),
                    $block('dos_donts', ['items' => [
                        ['kind' => 'do', 'assetId' => null, 'caption' => []],
                        ['kind' => 'dont', 'assetId' => null, 'caption' => []],
                    ]]),
                ],
            ],
            default => ['title' => [], 'blocks' => []],
        };
    }

    public static function newId(): string
    {
        return Str::lower(Str::random(12));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function text(string $key, int $max): array
    {
        return [
            $key => ['present', 'array:'.implode(',', Languages::codes())],
            "{$key}.*" => ['nullable', 'string', "max:{$max}"],
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
                'hex' => strtoupper((string) $swatch['hex']),
                'note' => self::cleanText($swatch['note']),
            ])],
            'type_pairing' => [
                'faces' => self::each($block['faces'], fn (array $face) => [
                    'script' => $face['script'],
                    'family' => trim(is_string($face['family'] ?? null) ? $face['family'] : ''),
                    'assetId' => self::cleanId($face['assetId'] ?? null),
                    'weight' => is_numeric($face['weight'] ?? null) ? (int) $face['weight'] : 400,
                    'sample' => trim(is_string($face['sample'] ?? null) ? $face['sample'] : ''),
                ]),
                'scale' => self::each($block['scale'], fn (array $row) => [
                    'label' => self::cleanText($row['label']),
                    'sizes' => array_map(
                        fn (mixed $size) => is_numeric($size) ? (int) $size : 16,
                        array_intersect_key(is_array($row['sizes'] ?? null) ? $row['sizes'] : [], array_flip(self::SCRIPTS)),
                    ),
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
        $type = $block['type'] ?? null;

        $items = match ($type) {
            'logos', 'dos_donts' => is_array($block['items'] ?? null) ? $block['items'] : [],
            default => [],
        };

        foreach ($items as $item) {
            if (is_array($item) && is_string($item['assetId'] ?? null)) {
                $slots[] = [$item['assetId'], 'image'];
            }
        }

        if ($type === 'type_pairing' && is_array($block['faces'] ?? null)) {
            foreach ($block['faces'] as $face) {
                if (is_array($face) && is_string($face['assetId'] ?? null)) {
                    $slots[] = [$face['assetId'], 'font'];
                }
            }
        }

        return $slots;
    }
}
