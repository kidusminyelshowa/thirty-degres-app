<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), $this->registration());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_signing_up_creates_the_company_workspace_with_the_new_user_as_owner()
    {
        $this->post(route('register.store'), $this->registration(['workspace_name' => 'Habesha Breweries']));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $membership = $this->membershipsOf($user)->sole();

        $this->assertSame('Habesha Breweries', $membership->workspace->name);
        $this->assertSame('habesha-breweries', $membership->workspace->slug);
        $this->assertSame(WorkspaceRole::Owner, $membership->role);
    }

    public function test_consent_to_storage_outside_ethiopia_is_required_and_recorded()
    {
        $this->post(route('register.store'), $this->registration(['data_transfer_consent' => null]))
            ->assertSessionHasErrors('data_transfer_consent');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

        $this->freezeTime();
        $this->post(route('register.store'), $this->registration());

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue(now()->startOfSecond()->equalTo($user->data_transfer_consent_at));
    }

    public function test_company_name_is_required()
    {
        $this->post(route('register.store'), $this->registration(['workspace_name' => '']))
            ->assertSessionHasErrors('workspace_name');

        $this->assertGuest();
    }

    public function test_company_names_in_amharic_get_a_working_address()
    {
        $this->post(route('register.store'), $this->registration(['workspace_name' => 'ቡና ቤት']));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $user->markEmailAsVerified();
        $workspace = $this->membershipsOf($user)->sole()->workspace;

        $this->assertSame('ቡና ቤት', $workspace->name);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $workspace->slug);
        $this->actingAs($user)
            ->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('currentWorkspace.name', 'ቡና ቤት'));
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @return array<string, string|null>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'workspace_name' => 'Test Company',
            'data_transfer_consent' => '1',
        ], $overrides);
    }
}
