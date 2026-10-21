<!DOCTYPE html>
@php
    // The signed-in user's text size, set here so the first paint is already
    // at that size; the font size control takes over from there.
    $fontScale = (int) (auth()->user()?->font_scale ?: 100);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if($fontScale !== 100) style="zoom: {{ $fontScale / 100 }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('logow.png') }}">
        <link rel="shortcut icon" href="{{ asset('logow.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        
        @if(app()->isLocal())
            @viteReactRefresh
        @endif
        
        @vite(['resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
