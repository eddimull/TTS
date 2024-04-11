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
    <link rel="stylesheet" href="{{ str_replace('//0.0.0.0:undefined', config('app.mix_url'), mix('css/app.css')) }}">

    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" href="favicon.png">

    <!-- Scripts -->
    @routes
    <script src="{{ str_replace('//0.0.0.0:undefined', config('app.mix_url'), mix('js/app.js')) }}" defer></script>
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>