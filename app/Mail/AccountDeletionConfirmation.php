<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $confirmationUrl,
    ) {}

    public function build()
    {
        return $this->markdown('email.account-deletion')
            ->with('name', $this->user->name)
            ->with('confirmationUrl', $this->confirmationUrl)
            ->subject('Confirm your TTS Bandmate account deletion');
    }
}
