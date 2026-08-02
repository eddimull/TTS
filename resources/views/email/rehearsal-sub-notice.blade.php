@component('mail::message')
# {{ $bandName }}

{{ $bodyText }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
