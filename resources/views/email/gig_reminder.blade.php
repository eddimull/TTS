@component('mail::message')
    <h2>Gig day!</h2>

    
        You are playing for {{$eventName}} today. 

@component('mail::button',['url'=> $advance])
Go to advance
@endcomponent

Good luck!
@endcomponent