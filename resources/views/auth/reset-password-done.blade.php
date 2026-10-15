<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,noarchive">
    <title>Password updated · BakiMate</title>
    @include('auth.partials.reset-style')
</head>
<body>
<main class="card">
    <div class="brand">BakiMate</div>
    <div class="ok">✓</div>
    <h1>Password updated</h1>
    <p class="ur" dir="rtl" lang="ur">پاس ورڈ بدل گیا</p>
    <p class="hint">Open the BakiMate app and sign in with your new password.</p>
    <a class="btn app" href="{{ config('bakimate.password_reset_app_scheme', 'bakimate') }}://login">Open BakiMate</a>
</main>
</body>
</html>
