<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unable to complete request</title>
    <style>
        body { margin: 0; padding: 24px; background: #f1f5f9; color: #1a2744; font: 16px/1.6 sans-serif; }
        main { max-width: 540px; margin: 12vh auto; padding: 32px; background: white; border-top: 4px solid #d4a843; }
        h1 { font-size: 24px; } a { color: #1a2744; font-weight: bold; }
    </style>
</head>
<body>
    <main>
        <h1>Unable to complete request</h1>
        <p>{{ $message }}</p>
        <p>Reference: {{ $status }}</p>
        <a href="/">Return to the application</a>
    </main>
</body>
</html>
