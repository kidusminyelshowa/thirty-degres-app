<?php

namespace Tests\Feature;

use App\Notifications\WorkspaceInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductNameTest extends TestCase
{
    public function test_pages_share_the_configured_product_name()
    {
        config(['app.name' => 'Aluma']);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('name', 'Aluma'));
    }

    public function test_invitation_email_uses_the_configured_product_name()
    {
        config(['app.name' => 'Aluma']);

        $notification = new WorkspaceInvitation(workspaceName: 'Habesha Coffee', inviterName: 'Abebe', token: 'token', expiresAt: now()->addWeek());
        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertSame('Join Habesha Coffee on Aluma', $mail->subject);
        $this->assertSame('Abebe invited you to the Habesha Coffee workspace on Aluma.', $mail->introLines[0]);
    }
}
