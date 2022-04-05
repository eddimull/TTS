@component('mail::message')
    <h2>Gig Week</h2>

    {{ $message }}

    
@foreach($events as $event)
[{{ $event->event_name }} ({{ $event->type }}) - {{ $event->formattedDate }}]({{$event->advance}})

@endforeach
    
@endcomponent