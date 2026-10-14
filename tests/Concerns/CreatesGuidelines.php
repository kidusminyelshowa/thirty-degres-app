<?php

namespace Tests\Concerns;

use App\Models\Brand;
use App\Models\GuidelinePage;
use App\Models\User;

/**
 * Guideline pages for tests, written from inside their workspace. Use with
 * CreatesAssets and CreatesWorkspaces.
 */
trait CreatesGuidelines
{
    /**
     * @param  list<array<string, mixed>>|null  $blocks
     */
    protected function createGuidelinePage(
        Brand $brand,
        ?User $author = null,
        string $title = 'Logo',
        ?array $blocks = null,
        ?User $publisher = null,
        int $position = 0,
    ): GuidelinePage {
        $workspace = $this->workspaceOf($brand);
        $author ??= $this->ownerOf($workspace);
        $blocks ??= [['id' => 'intro', 'type' => 'text', 'text' => ['en' => 'Use the logo on white.', 'am' => 'አርማውን በነጭ ላይ ተጠቀሙ።']]];

        return $this->inWorkspace($workspace, function () use ($brand, $author, $title, $blocks, $publisher, $position) {
            $page = GuidelinePage::create([
                'brand_id' => $brand->id,
                'position' => $position,
                'title' => ['en' => $title, 'am' => 'አርማ'],
                'blocks' => $blocks,
                'created_by' => $author->id,
                'edited_by' => $author->id,
                'edited_at' => now(),
            ]);

            if ($publisher !== null) {
                $page->published_title = $page->title;
                $page->published_blocks = $page->blocks;
                $page->published_by = $publisher->id;
                $page->published_at = now();
                $page->save();
            }

            return $page;
        });
    }
}
