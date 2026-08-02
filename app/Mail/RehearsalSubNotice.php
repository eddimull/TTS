<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Short plain notice to a rehearsal sub (removed / cancelled / restored).
 */
class RehearsalSubNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $bandName,
    ) {}

    public function build()
    {
        return $this->markdown('email.rehearsal-sub-notice')
            ->with([
                'bodyText' => $this->bodyText,
                'bandName' => $this->bandName,
            ])
            ->subject($this->subjectLine);
    }
}
