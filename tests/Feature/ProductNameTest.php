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
        config(['app.name' => 'witti']);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('name', 'witti'));
    }

    public function test_amharic_texts_take_the_product_name_from_config_rather_than_an_old_name()
    {
        $this->withCookie('locale', 'am')
            ->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'am')
                ->where('translations.New to :app?', ':app አዲስ ነዎት?'));

        $amharic = (string) file_get_contents(lang_path('am.json'));
        $this->assertStringNotContainsStringIgnoringCase('Thirty Degres', $amharic);
        $this->assertStringNotContainsStringIgnoringCase('Aluma', $amharic);
    }

    public function test_invitation_email_uses_the_configured_product_name()
    {
        config(['app.name' => 'witti']);

        $notification = new WorkspaceInvitation(workspaceName: 'Habesha Coffee', inviterName: 'Abebe', token: 'token', expiresAt: now()->addWeek());
        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertSame('Join Habesha Coffee on witti', $mail->subject);
        $this->assertSame('Abebe invited you to the Habesha Coffee workspace on witti.', $mail->introLines[0]);
    }
}
