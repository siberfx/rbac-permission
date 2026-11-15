<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Access control')</title>
    <style>
        :root {
            --bg: #f7f7f8; --fg: #1d1d1f; --muted: #6b6b76; --line: #e2e2e8; --accent: #3151d3; --danger: #b42318;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) { --bg: #121316; --fg: #ececf1; --muted: #9a9aa6; --line: #2a2b31; --accent: #7b93ff; --danger: #ff8a80; }
        }
        :root[data-theme="dark"] { --bg: #121316; --fg: #ececf1; --muted: #9a9aa6; --line: #2a2b31; --accent: #7b93ff; --danger: #ff8a80; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 15px/1.5 system-ui, sans-serif; }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        nav a { margin-right: 14px; color: var(--accent); }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        input, select, button { font: inherit; padding: 6px 10px; border: 1px solid var(--line); border-radius: 6px; background: transparent; color: var(--fg); }
        button { background: var(--accent); color: #fff; border: 0; cursor: pointer; }
        button.danger { background: var(--danger); }
        .muted { color: var(--muted); }
        .error { color: var(--danger); }
        form.inline { display: inline; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        @media (max-width: 600px) { main { padding: 16px; } th, td { font-size: 14px; } }
    </style>
</head>
<body>
<main>
    <nav><a href="{{ route('rbac.roles.index') }}">Roles</a></nav>

    @if (session('status'))
        <p>{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul class="error">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @yield('content')
</main>
</body>
</html>
