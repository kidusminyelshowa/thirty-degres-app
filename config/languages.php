<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content languages
    |--------------------------------------------------------------------------
    |
    | Languages a brand can write its guidelines in. Each brand picks some of
    | these, in order; the first is the one shown when a text has not been
    | written in the reader's language. Add a language by adding a line:
    | the code (ISO 639-1), its name in English and in itself, its script
    | (latin, ethiopic or arabic) and its direction.
    |
    */

    'content' => [
        'am' => ['name' => 'Amharic', 'native' => 'አማርኛ', 'script' => 'ethiopic', 'dir' => 'ltr'],
        'en' => ['name' => 'English', 'native' => 'English', 'script' => 'latin', 'dir' => 'ltr'],
        'om' => ['name' => 'Afaan Oromoo', 'native' => 'Afaan Oromoo', 'script' => 'latin', 'dir' => 'ltr'],
        'ti' => ['name' => 'Tigrinya', 'native' => 'ትግርኛ', 'script' => 'ethiopic', 'dir' => 'ltr'],
        'so' => ['name' => 'Somali', 'native' => 'Af-Soomaali', 'script' => 'latin', 'dir' => 'ltr'],
        'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili', 'script' => 'latin', 'dir' => 'ltr'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'script' => 'arabic', 'dir' => 'rtl'],
        'fr' => ['name' => 'French', 'native' => 'Français', 'script' => 'latin', 'dir' => 'ltr'],
        'aa' => ['name' => 'Afar', 'native' => 'Qafaraf', 'script' => 'latin', 'dir' => 'ltr'],
    ],

    // New brands write their guidelines in these until a Brand manager
    // changes them.
    'default' => ['am', 'en'],

    'max_per_brand' => 8,

];
