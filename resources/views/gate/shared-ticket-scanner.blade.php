<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ticket Scanner | eLive Events</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/filament/admin/theme.css', 'resources/js/ticket-scanner.js'])
    @livewireStyles
</head>
<body class="elive-shared-scanner-page">
    <main>
        @include('filament.pages.partials.ticket-scanner-workspace', ['withAttendance' => false])
    </main>
    @livewireScripts
</body>
</html>
