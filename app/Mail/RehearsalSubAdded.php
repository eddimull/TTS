<?php

namespace App\Mail;

use App\Formatters\NoteText;
use App\Models\Bands;
use App\Models\Rehearsal;
use App\Models\RehearsalSub;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class RehearsalSubAdded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RehearsalSub $sub,
        public Rehearsal $rehearsal,
        public Bands $band,
        public ?string $date,
    ) {}

    public function build()
    {
        $event = $this->rehearsal->events->first();
        $time  = $event?->start_time?->format('g:i A');
        $venue = $this->rehearsal->venue_name
            ?? $this->rehearsal->rehearsalSchedule?->location_name;

        return $this->markdown('email.rehearsal-sub-added')
            ->with([
                'subName'  => $this->sub->name,
                'bandName' => $this->band->name,
                'roleName' => $this->sub->bandRole?->name,
                'dateText' => $this->date
                    ? Carbon::parse($this->date)->format('l, F j, Y')
                    : 'TBD',
                'timeText' => $time ?? 'TBD',
                'venue'    => $venue ?? 'TBD',
                'notes'    => NoteText::toPlainText($this->rehearsal->notes),
            ])
            ->subject("Rehearsal invitation from {$this->band->name}");
    }
}
