<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

        <title inertia>{{ config('app.name', 'Nativa ERP') }}</title>

        @vite(['resources/js/app.js', 'resources/css/app.css'])

        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900">
        @inertia
    </body>
</html>
