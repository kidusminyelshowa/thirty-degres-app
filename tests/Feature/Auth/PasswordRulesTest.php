<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->detectEnvironment(fn () => 'production');

        // The leaked-password check asks Have I Been Pwned; answer "not found".
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    public function test_a_browser_suggested_password_without_symbols_is_accepted(): void
    {
        $this->assertTrue($this->passes('xatqeb7fusmyBymwah'));
    }

    public function test_short_passwords_are_still_refused(): void
    {
        $this->assertFalse($this->passes('Short1!'));
    }

    public function test_browsers_are_not_told_to_add_symbols(): void
    {
        $this->assertStringNotContainsString('special', Password::defaults()->toPasswordRulesString());
    }

    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => Password::default()])->passes();
    }
}
