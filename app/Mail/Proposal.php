<?php

namespace App\Mail;

use App\Models\Proposals;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Proposal extends Mailable
{
    use Queueable, SerializesModels;

    public $proposal;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Proposals $proposal, $message)
    {
        $this->proposal = $proposal;
        $this->message = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = 'Proposal with ' . $this->proposal->band->name . ' to play at ' . $this->proposal->location;
        if(empty($this->proposal->location))
        {
            $subject = 'Proposal with ' . $this->proposal->band->name;
        }
        return $this->markdown('email.proposal')
                        ->with('proposal',$this->proposal)
                        ->with('message',$this->message)
                        ->subject($subject);
    }
}
