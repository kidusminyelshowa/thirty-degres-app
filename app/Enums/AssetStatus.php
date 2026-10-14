<?php

namespace App\Enums;

/**
 * Uploads start as drafts. A Brand manager who did not upload the file
 * approves it; only then can Viewers see it. Archived files are hidden from
 * everyone but Brand managers.
 */
enum AssetStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';
}
