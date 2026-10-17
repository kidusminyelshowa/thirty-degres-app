<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_signed_in_people_are_sent_to_their_workspace(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_sign_in_page_carries_no_starter_kit_branding(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Laravel')
            ->assertDontSee('Laracasts');
    }
}
