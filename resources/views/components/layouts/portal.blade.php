<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Soundmine Platform</title>
    <meta name="referrer" content="no-referrer">
    <style>body{font-family:system-ui,sans-serif;max-width:60rem;margin:2rem auto;padding:0 1rem;line-height:1.5}label,input,button{display:block;margin:.5rem 0}input{padding:.4rem;max-width:100%}dt{font-weight:600}dd{margin-bottom:.75rem}</style>
    @livewireStyles
</head>
<body>
    <header><a href="/">Soundmine</a></header>
    <main>{{ $slot }}</main>
    @livewireScripts
</body>
</html>
