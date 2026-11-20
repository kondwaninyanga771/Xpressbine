<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Xpressbine books local trades in Lusaka. Pay with mobile money into escrow.">
        <title>{{ config('app.name', 'Xpressbine') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body class="bg-sand font-sans text-ink antialiased">
        @inertia
    </body>
</html>
