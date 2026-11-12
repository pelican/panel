{{-- Prerendered by `artisan down` during a panel update, so it must not depend on anything from the app. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="15">
    <title>{{ config('app.name', 'Pelican') }} is updating</title>
    <style>
        :root { color-scheme: light dark; --bg: #f4f4f5; --fg: #18181b; --muted: #71717a; --card: #ffffff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #09090b; --fg: #fafafa; --muted: #a1a1aa; --card: #18181b; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--fg); font-family: ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 28rem; margin: 1rem; padding: 2rem; border-radius: 0.75rem; background: var(--card); text-align: center; }
        h1 { margin: 0 0 0.5rem; font-size: 1.25rem; }
        p { margin: 0; color: var(--muted); line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name', 'Pelican') }} is updating</h1>
        <p>This usually takes a few minutes. The page will reload by itself once the update is done.</p>
    </main>
</body>
</html>
