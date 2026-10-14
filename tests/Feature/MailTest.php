<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_mailer_is_available_in_production_config()
    {
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key',
        ]);

        $this->assertInstanceOf(ResendTransport::class, Mail::mailer()->getSymfonyTransport());
    }

    public function test_new_users_are_sent_a_verification_email()
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Abebe Bikila',
            'email' => 'abebe@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'workspace_name' => 'Bikila Running Club',
            'data_transfer_consent' => '1',
        ]);

        $user = User::where('email', 'abebe@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
