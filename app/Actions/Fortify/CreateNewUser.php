<?php

namespace App\Actions\Fortify;

use App\Actions\Workspaces\AcceptInvitation;
use App\Actions\Workspaces\CreateWorkspace;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Workspaces\AcceptInvitationController;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private CreateWorkspace $createWorkspace,
        private AcceptInvitation $acceptInvitation,
    ) {}

    /**
     * Validate and create a newly registered user, with either their own
     * company workspace or a place in the workspace that invited them.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $pending = $this->pendingInvitation();
        $invitation = $pending !== null && strcasecmp($pending->email, (string) ($input['email'] ?? '')) === 0 ? $pending : null;

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'workspace_name' => [$invitation === null ? 'required' : 'nullable', 'string', 'max:120'],
            // Accounts and files are stored in Frankfurt during the pilot, so
            // people agree to storage outside Ethiopia (docs/data-residency.md).
            'data_transfer_consent' => ['accepted'],
        ], [
            'workspace_name.required' => __('Enter your company name.'),
            'data_transfer_consent.accepted' => __('Please agree to where your data is stored to continue.'),
        ]);

        // Someone signing up from an invitation link with a different address
        // would otherwise only be asked for a company name the page hides.
        $validator->after(function (ValidatorInstance $validator) use ($pending, $invitation): void {
            if ($pending !== null && $invitation === null) {
                $validator->errors()->add('email', __('This invitation was sent to :email. Sign up with that address to join :workspace.', [
                    'email' => $pending->email,
                    'workspace' => $pending->workspace->name,
                ]));
            }
        });

        $validator->validate();

        return DB::transaction(function () use ($input, $invitation) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->forceFill(['data_transfer_consent_at' => now()])->save();

            if ($invitation !== null) {
                $this->acceptInvitation->handle($invitation, $user);
                session()->forget(AcceptInvitationController::SESSION_KEY);
            } else {
                $this->createWorkspace->handle($user, $input['workspace_name']);
            }

            return $user;
        });
    }

    /**
     * The still-valid invitation link this person opened before signing up.
     */
    private function pendingInvitation(): ?Invitation
    {
        $token = session(AcceptInvitationController::SESSION_KEY);

        return is_string($token) ? $this->acceptInvitation->find($token) : null;
    }
}
