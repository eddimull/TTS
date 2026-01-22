@component('mail::message')
# Substitute Invitation for {{ $bandName }}

Hello! You've been invited to substitute for **{{ $bandName }}** at an upcoming event.

## Event Details

**Event:** {{ $eventName }}
**Date:** {{ $eventDate }}
**Time:** {{ $eventTime }}@if($eventEndTime) - {{ $eventEndTime }}@endif
**Location:** {{ $eventLocation }}

@if($roleName)
**Instrument/Role:** {{ $roleName }}
@endif
**Payout:** {{ $payoutAmount }}

@if(count($charts) > 0 || count($songs) > 0)

## Music Selection

@if(count($charts) > 0)
**Charts:**
@foreach($charts as $chart)
- {{ $chart->title ?? $chart['title'] ?? 'Untitled' }}@if(isset($chart->composer) || isset($chart['composer'])) - {{ $chart->composer ?? $chart['composer'] }}@endif
@endforeach
@endif

@if(count($songs) > 0)
**Songs:**
@foreach($songs as $song)
- {{ $song->title ?? $song['title'] ?? 'Untitled' }}
@endforeach
@endif
@endif

@if($notes)

**Notes:**
{{ $notes }}
@endif

---

@if($isRegisteredUser)
**You already have an account!** Click the button below to view the event details, charts, and roster information.
@else
**You'll need to create an account** to view event details, download charts, and see the roster.
@endif

@component('mail::button', ['url' => $invitationLink])
View Event Details
@endcomponent

Thanks,<br>
{{ $bandName }}
@endcomponent
