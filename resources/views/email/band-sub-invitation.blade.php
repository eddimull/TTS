@component('mail::message')
# You've been added as a sub for {{ $bandName }}

Hello! You've been added as a substitute for **{{ $bandName }}**.

@if($roleName)
**Instrument/Role:** {{ $roleName }}
@endif

@if($notes)
**Notes:**
{{ $notes }}
@endif

---

@if($isRegisteredUser)
**You already have an account!** Click the button below to confirm and start subbing for this band.
@else
**You'll need to create an account** to confirm and start subbing for this band.
@endif

@component('mail::button', ['url' => $invitationLink])
Accept Invitation
@endcomponent

Thanks,<br>
{{ $bandName }}
@endcomponent
