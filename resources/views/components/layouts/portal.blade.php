<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TheSoundMine Customer Portal</title>
    <meta name="referrer" content="no-referrer">
    <x-brand.assets />
    @livewireStyles
</head>
<body class="tsm-page">
    <x-brand.header />
    <main class="tsm-shell tsm-portal">{{ $slot }}</main>
    @livewireScripts
</body>
</html>
