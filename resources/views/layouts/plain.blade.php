<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <meta name="theme-color" content="#2d343a">
    <meta name="color-scheme" content="light">
    <link rel="icon" href="{{ asset('icons/icon-96.png') }}">
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version') }}">
</head>
<body class="app-body">
<main class="auth-page" id="main">
    @yield('content')
</main>
</body>
</html>
