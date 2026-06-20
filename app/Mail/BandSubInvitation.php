<?php

namespace App\Mail;

use App\Models\BandSubInvitation as BandSubInvitationModel;
use App\Models\Bands;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BandSubInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;
    public $band;
    public $invitationUrl;

    /**
     * Create a new message instance.
     *
     * @param BandSubInvitationModel $invitation
     * @param Bands $band
     * @param string $invitationUrl
     * @return void
     */
    public function __construct(BandSubInvitationModel $invitation, Bands $band, string $invitationUrl)
    {
        $this->invitation = $invitation;
        $this->band = $band;
        $this->invitationUrl = $invitationUrl;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('email.band-sub-invitation')
            ->with([
                'bandName' => $this->band->name,
                'roleName' => $this->invitation->role_name,
                'notes' => $this->invitation->notes,
                'invitationLink' => $this->invitationUrl,
                'isRegisteredUser' => $this->invitation->isRegisteredUser(),
            ])
            ->subject("You've been added as a sub for {$this->band->name}");
    }
}
