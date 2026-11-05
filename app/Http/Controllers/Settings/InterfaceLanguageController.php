<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Localisation\InterfaceLanguages;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Switch the app between its interface languages. Works signed out too, so
 * the sign-in page and shared links can be read in Amharic. Signed in, the
 * choice is saved on the account and follows the person to other devices
 * and into their emails.
 */
class InterfaceLanguageController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(InterfaceLanguages::codes())],
        ]);

        $user = $request->user();

        if ($user instanceof User) {
            $user->forceFill(['locale' => $validated['locale']])->save();
        }

        Cookie::queue(InterfaceLanguages::COOKIE, $validated['locale'], 60 * 24 * 365);

        return back();
    }
}
