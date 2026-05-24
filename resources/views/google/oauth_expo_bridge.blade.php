<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,noarchive">
    <title>{{ config('app.name', 'Laravel') }}</title>
</head>
<body style="margin:0;font-family:system-ui,sans-serif;padding:24px;text-align:center;">
<script>
(function () {
    var deep = @json(config('bakimate.google_expo_oauth_deep_link', 'bakimate://oauthredirect'));
    var qs = window.location.search || '';
    var h = window.location.hash || '';
    if (qs.length > 0 || h.length > 0) {
        window.location.replace(deep + qs + h);
        return;
    }
    document.body.innerHTML += '<p>Missing OAuth response — you can close this tab and reopen the app.</p>';
})();
</script>
<noscript>Enable JavaScript to return to the app.</noscript>
</body>
</html>
