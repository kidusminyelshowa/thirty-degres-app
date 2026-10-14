<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public string $workspaceName,
        public string $inviterName,
        public string $token,
        public CarbonInterface $expiresAt,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Join :workspace on Thirty Degres', ['workspace' => $this->workspaceName]))
            ->line(__(':inviter invited you to the :workspace workspace on Thirty Degres.', [
                'inviter' => $this->inviterName,
                'workspace' => $this->workspaceName,
            ]))
            ->action(__('Accept invitation'), route('invitations.show', $this->token))
            ->line(__('This link works until :date. If you were not expecting it, you can ignore this email.', [
                'date' => $this->expiresAt->toFormattedDayDateString(),
            ]));
    }
}
