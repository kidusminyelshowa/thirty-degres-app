<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Enums\WorkspaceRole;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * A workspace always keeps an Owner, so its last Owner hands over first.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();

            if (! $user instanceof User || $validator->errors()->isNotEmpty()) {
                return;
            }

            $ownerships = $user->memberships()->where('role', WorkspaceRole::Owner)->with('workspace')->get();

            foreach ($ownerships as $ownership) {
                $hasOtherOwner = app(Tenancy::class)->run($ownership->workspace, fn () => Membership::query()
                    ->where('role', WorkspaceRole::Owner)
                    ->whereKeyNot($ownership->getKey())
                    ->exists());

                if (! $hasOtherOwner) {
                    $validator->errors()->add('password', __('You are the only Owner of :workspace. Make someone else Owner before deleting your account.', [
                        'workspace' => $ownership->workspace->name,
                    ]));

                    return;
                }
            }
        }];
    }
}
