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

    <!-- @vite(['resources/css/app.css', 'resources/js/app.js']) -->
    <script>
        {!! file_get_contents(resource_path('tailwind/tailwind.min.js')) !!}
    </script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Nunito'],
                    },
                    colors: {
                        blue: {
                            200: '#bee3f8',
                            800: '#2c5282',
                        }
                    },
                },
            },
        }
    </script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" href="favicon.png">
</head>

<body style="{{ $bodyStyle ?? 'width: 22cm;' }}" class="font-sans antialiased">
    <div class="{{ $contentClasses ?? 'min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8'}}">
        @yield('content')
    </div>
</body>

</html>