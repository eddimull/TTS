@component('mail::message')
    <h2>Hello, you were invited to become {{$ownerMember}} of {{$bandName}}!</h2>

    
        To accept create an account below. 
    
    

@component('mail::button',['url'=>config('app.url') . '/register/' ])
Join Band
@endcomponent

Thanks,<br>
{{$bandName}}
@endcomponent