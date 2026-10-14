<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asset library
    |--------------------------------------------------------------------------
    |
    | Files go to the default filesystem disk (FILESYSTEM_DISK): the local
    | disk in development, an S3-compatible bucket in production. The upload
    | limit must also fit within PHP's upload_max_filesize and post_max_size.
    |
    */

    'max_upload_megabytes' => (int) env('ASSET_MAX_UPLOAD_MB', 50),

    // Brand files people actually exchange. Anything else is refused, so the
    // library cannot be used to pass around programs or web pages.
    'extensions' => [
        // Logos and graphics
        'svg', 'eps', 'ai', 'pdf', 'png',
        // Photos and images
        'jpg', 'jpeg', 'webp', 'gif', 'tif', 'tiff', 'heic',
        // Fonts
        'ttf', 'otf', 'woff', 'woff2',
        // Documents and design files
        'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'psd', 'indd', 'fig', 'sketch',
        // Video and sound
        'mp4', 'mov', 'mp3', 'wav',
        // Packs
        'zip',
    ],

];
