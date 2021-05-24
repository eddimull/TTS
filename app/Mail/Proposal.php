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
    public function __construct(Proposals $proposal)
    {
        $this->proposal = $proposal;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('email.proposal')->subject('Proposal for ' . $this->proposal->band->name);
    }
}
