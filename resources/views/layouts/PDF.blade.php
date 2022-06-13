<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        <!-- Styles -->
        <link rel="stylesheet" href="{{ mix('css/app.css') }}">
        <script src="{{ mix('js/app.js') }}" defer></script>

        <link rel="icon" type="image/svg+xml" href="favicon.svg">
        <link rel="icon" type="image/png" href="favicon.png">
    </head>
    <body style="width: 22cm;" class="font-sans antialiased">
        <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{$slot}}
        </div>
    </body>
</html>
