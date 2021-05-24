@component('mail::message')
    <h2>Hello, thanks for starting up a proposal with {{$proposal->band->name}}!</h2>

    
        When: {{ date('m/d/Y g:i A',strtotime($proposal->date)) }}
    
        How long: {{$proposal->hours}} hours
    
        For what: {{$proposal->event_type->name}}
    
        Price: ${{$proposal->price}}
    
    

@component('mail::button',['url'=>config('app.url') . '/proposals/' . $proposal->key . '/details' ])
Confirm or Deny Proposal
@endcomponent

Thanks,<br>
{{$proposal->band->name}}
@endcomponent