<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Invitation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($band,$owner = false)
    {
        //
        $this->band = $band;
        $this->verbage = [
            'type' => 'owner',
            'language' => 'an owner'
        ];
        if(!$owner)
        {
            $this->verbage = [
                'type' => 'member',
                'language' => 'a member'
            ];
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('email.invitation')
                    ->with('bandName',$this->band->name)
                    ->with('ownerMember',$this->verbage['language'])
                    ->subject('Invite to become ' . $this->verbage['language'] . ' of ' . $this->band->name);
                    
    }
}
