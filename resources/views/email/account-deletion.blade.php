@component('mail::message')
# Confirm account deletion

Hi {{ $name }},

We received a request to permanently delete your TTS Bandmate account. This will
remove your profile and detach you from your bands. **This cannot be undone.**

If you made this request, confirm below. The link expires in 60 minutes.

@component('mail::button', ['url' => $confirmationUrl, 'color' => 'error'])
Delete my account
@endcomponent

If you did **not** request this, you can safely ignore this email — your account
will not be deleted.

Thanks,<br>
TTS Bandmate
@endcomponent
