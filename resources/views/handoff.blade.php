<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Continue to workspace</title>
</head>
<body>
    <main>
        <h1>Continue to workspace</h1>
        <p>Continue to sign in to this workspace.</p>
        <a href="{{ $continueUrl }}">Continue</a>
    </main>
</body>
</html>
