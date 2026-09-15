<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — NyamanSuperApp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0710; --surface: #16101f;
            --accent: #a78bfa; --accent-600: #8b5cf6;
            --grad: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
            --grad-bright: linear-gradient(135deg, #a78bfa 0%, #7c3aed 100%);
            --grad-rev: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
            --accent-soft: rgba(167,139,250,.13);
            --glow: rgba(139,92,246,.45);
            --text: #f6f4fb; --muted: #9b93ad;
        }
        html { color-scheme: dark; }
        * { box-sizing: border-box; }
@include('partials.icon-css')
        body {
            margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; padding: 24px;
            color: var(--text); font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background:
                radial-gradient(760px 480px at 50% -10%, rgba(139,92,246,.24), transparent 62%),
                radial-gradient(600px 420px at -10% 110%, rgba(109,40,217,.18), transparent 60%),
                var(--bg);
        }
        .code { font-variant-numeric: tabular-nums; font-size: 4rem; font-weight: 700; color: var(--accent); letter-spacing: .04em; }
        h1 { margin: 8px 0 10px; font-size: 1.4rem; font-weight: 800; letter-spacing: -.02em; }
        p { margin: 0 0 28px; color: var(--muted); max-width: 420px; line-height: 1.6; }
        a.btn {
            display: inline-flex; padding: 13px 26px; border-radius: 999px; text-decoration: none;
            background: var(--grad); color: #fff; font-weight: 700;
            box-shadow: 0 14px 40px -14px var(--glow); transition: background .2s, transform .15s;
        }
        a.btn:hover { background: var(--grad-rev); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="code">403</div>
    <h1>Akses ditolak</h1>
    <p>{{ $exception->getMessage() !== '' ? $exception->getMessage() : 'Anda tidak punya akses ke halaman ini.' }}</p>
    <a class="btn" href="{{ auth()->check() ? route('dashboard') : route('welcome') }}">Kembali</a>
</body>
</html>
