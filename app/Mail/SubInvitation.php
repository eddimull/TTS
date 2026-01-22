<?php

namespace App\Mail;

use App\Models\EventSubs;
use App\Models\Events;
use App\Models\Bands;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $eventSub;
    public $event;
    public $band;
    public $invitationUrl;

    /**
     * Create a new message instance.
     *
     * @param EventSubs $eventSub
     * @param Events $event
     * @param Bands $band
     * @param string $invitationUrl
     * @return void
     */
    public function __construct(EventSubs $eventSub, Events $event, Bands $band, string $invitationUrl)
    {
        $this->eventSub = $eventSub;
        $this->event = $event;
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
        $eventName = $this->event->title ?? 'Upcoming Event';
        $eventDate = $this->event->date ? $this->event->date->format('F j, Y') : 'TBD';

        // Extract role name
        $roleName = $this->eventSub->role_name;

        // Extract charts and songs from event's additional_data
        $charts = [];
        $songs = [];
        $endTime = null;

        if ($this->event->additional_data) {
            $additionalData = is_string($this->event->additional_data)
                ? json_decode($this->event->additional_data)
                : (object) $this->event->additional_data;

            // Check for booking events (nested in performance object)
            if (isset($additionalData->performance)) {
                $charts = $additionalData->performance->charts ?? [];
                $songs = $additionalData->performance->songs ?? [];
            }
            // Check for rehearsal events (directly in additional_data)
            else {
                $charts = $additionalData->charts ?? [];
                $songs = $additionalData->songs ?? [];
            }

            // Extract end time from timeline
            if (isset($additionalData->times) && is_array($additionalData->times) && count($additionalData->times) > 0) {
                $times = collect($additionalData->times)->sortBy('time');
                $endTime = \Carbon\Carbon::parse($times->last()->time)->format('g:i A');
            }
        }

        // Format start time
        $startTime = 'TBD';
        if ($this->event->time) {
            $startTime = is_string($this->event->time)
                ? \Carbon\Carbon::parse($this->event->time)->format('g:i A')
                : $this->event->time->format('g:i A');
        }

        return $this->markdown('email.sub-invitation')
            ->with([
                'bandName' => $this->band->name,
                'eventName' => $eventName,
                'eventDate' => $eventDate,
                'eventTime' => $startTime,
                'eventEndTime' => $endTime,
                'eventLocation' => $this->event->location ?? 'Location TBD',
                'roleName' => $roleName,
                'payoutAmount' => $this->eventSub->payout_amount ? '$' . number_format($this->eventSub->payout_amount / 100, 2) : 'TBD',
                'notes' => $this->eventSub->notes,
                'charts' => $charts,
                'songs' => $songs,
                'invitationLink' => $this->invitationUrl,
                'isRegisteredUser' => $this->eventSub->isRegisteredUser(),
            ])
            ->subject("Substitute Invitation for {$this->band->name} - {$eventName}");
    }
}
