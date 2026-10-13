<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_redirects_to_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings')
            ->assertRedirect('/settings/profile');
    }

    public function test_settings_requires_authentication(): void
    {
        $this->get('/settings')->assertRedirect(route('login'));
    }
}
