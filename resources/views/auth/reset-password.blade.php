<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,noarchive">
    <title>Reset password · BakiMate</title>
    @include('auth.partials.reset-style')
</head>
<body>
<main class="card">
    <div class="brand">BakiMate</div>
    <h1>Reset your password</h1>
    <p class="ur" dir="rtl" lang="ur">نیا پاس ورڈ بنائیں</p>

    <a class="btn app" href="{{ $appLink }}">Open in BakiMate app</a>
    <p class="hint">On your phone with BakiMate installed? Tap above. Otherwise, set a new password here:</p>

    @if (!empty($linkError))
        <div class="alert">This reset link is invalid or has expired. Request a new reset from the app's sign-in screen.</div>
    @endif

    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.update.web') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
        </label>
        <label>New password <span class="sub">(at least 8 characters)</span>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </label>
        <label>Confirm new password
            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </label>
        <button class="btn" type="submit">Update password</button>
    </form>
</main>
</body>
</html>
