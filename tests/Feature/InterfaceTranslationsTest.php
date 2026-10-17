<?php

namespace Tests\Feature;

use App\Localisation\InterfaceLanguages;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Every text the app shows has a translation in each interface language
 * (CLAUDE.md rule 7), so a new English string without its Amharic turns CI
 * red instead of showing up in English on an Amharic screen.
 */
class InterfaceTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_interface_language_has_a_file_and_it_is_complete()
    {
        $strings = $this->sourceStrings();
        $this->assertGreaterThan(300, count($strings), 'The string scan found too little; check its patterns.');

        foreach (InterfaceLanguages::codes() as $code) {
            if ($code === 'en') {
                continue;
            }

            $path = lang_path("{$code}.json");
            $this->assertFileExists($path);

            /** @var array<string, string> $translations */
            $translations = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

            $missing = array_values(array_filter(
                array_keys($strings),
                fn (string $text) => ! isset($translations[$text]) || trim($translations[$text]) === '',
            ));

            $this->assertSame([], $missing, "lang/{$code}.json is missing these texts:\n".implode("\n", array_map(
                fn (string $text) => "  {$text}  ({$strings[$text]})",
                $missing,
            )));

            foreach ($translations as $english => $translated) {
                $this->assertSame(
                    $this->placeholders($english),
                    $this->placeholders($translated),
                    "lang/{$code}.json changes the :placeholders of \"{$english}\"",
                );
            }
        }
    }

    public function test_someone_signed_out_switches_language_and_it_is_remembered_in_a_cookie()
    {
        $this->put(route('language.update'), ['locale' => 'am'])
            ->assertRedirect()
            ->assertCookie('locale', 'am');

        $this->withCookie('locale', 'am')
            ->get(route('login'))
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'am')
                ->where('translations.Log in', 'ግባ'))
            ->assertSee('<html lang="am"', false);

        $this->put(route('language.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_the_browser_language_is_used_until_someone_chooses()
    {
        $this->get(route('login'), ['Accept-Language' => 'am-ET,am;q=0.9,en;q=0.8'])
            ->assertInertia(fn ($page) => $page->where('locale', 'am'));

        $this->get(route('login'), ['Accept-Language' => 'fr-FR'])
            ->assertInertia(fn ($page) => $page->where('locale', 'en')->where('translations', []));
    }

    public function test_a_signed_in_choice_is_saved_on_the_account_and_used_for_their_emails()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('language.update'), ['locale' => 'am']);

        $this->assertSame('am', $user->fresh()?->locale);
        $this->assertSame('am', $user->fresh()?->preferredLocale());

        // Their choice wins over the browser, on any device.
        $this->get(route('profile.edit'), ['Accept-Language' => 'en'])
            ->assertInertia(fn ($page) => $page->where('locale', 'am'));
    }

    /**
     * Texts passed to t() in the screens and __() in the PHP code, with the
     * file each was first seen in.
     *
     * @return array<string, string>
     */
    private function sourceStrings(): array
    {
        $literal = '(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)")';
        $found = [];

        $scan = function (string $directory, array $extensions, string $call) use ($literal, &$found): void {
            /** @var list<SplFileInfo> $files */
            $files = File::allFiles(base_path($directory));

            foreach ($files as $file) {
                if (! in_array($file->getExtension(), $extensions, true) || str_contains($file->getPathname(), '/routes/') || str_contains($file->getPathname(), '/actions/')) {
                    continue;
                }

                preg_match_all('/(?<![\w.$>:])'.$call.'\(\s*'.$literal.'/s', $file->getContents(), $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $text = stripcslashes(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''));

                    if ($text !== '') {
                        $found[$text] ??= $file->getRelativePathname();
                    }
                }
            }
        };

        $scan('resources/js', ['ts', 'tsx'], 't');
        $scan('app', ['php'], '__');

        return $found;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-zA-Z]+/', $text, $matches);
        $names = array_values(array_unique($matches[0]));
        sort($names);

        return $names;
    }
}
