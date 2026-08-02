@component('mail::message')
# Rehearsal invitation

Hi {{ $subName }},

You've been added as a substitute{{ $roleName ? " ({$roleName})" : '' }} for a rehearsal with **{{ $bandName }}**.

- **Date:** {{ $dateText }}
- **Time:** {{ $timeText }}
- **Location:** {{ $venue }}
@if($notes)

**Notes:** {{ $notes }}
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
