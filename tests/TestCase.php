<?php

namespace Tests;

use App\Tenancy\DatabaseRole;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * RefreshDatabase migrates in-process, which needs the table owner.
     * Everything after that runs as the restricted app role, like
     * production, so the row-level security policies are really exercised.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if (RefreshDatabaseState::$migrated || ! in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            return;
        }

        $role = $this->app->make(DatabaseRole::class);
        $role->suspend();

        Event::listen(DatabaseRefreshed::class, fn () => $role->resume());
    }
}
