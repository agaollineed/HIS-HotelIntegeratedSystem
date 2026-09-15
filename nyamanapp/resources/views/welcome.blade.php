<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NyamanSuperApp — Kelola Penginapan dalam Satu Aplikasi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --border: rgba(255,255,255,.09);
            --accent-2: #d8b4fe; --accent-2-600: #c084fc;
            --accent-2-glow: rgba(216, 180, 254, .30);
            --border-violet: rgba(216, 180, 254, .28);
            --accent-2-soft: rgba(216, 180, 254, .12);
        }
        html { color-scheme: dark; }
        * { box-sizing: border-box; }
@include('partials.icon-css')
        body {
            margin: 0; min-height: 100vh; color: var(--text);
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; padding: 24px;
            background:
                radial-gradient(860px 520px at 50% -8%, rgba(139,92,246,.28), transparent 62%),
                radial-gradient(640px 440px at 100% 110%, rgba(109,40,217,.22), transparent 58%),
                var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px; margin-bottom: 22px;
            font-variant-numeric: tabular-nums; font-size: .74rem; letter-spacing: .18em;
            text-transform: uppercase; color: var(--accent);
            padding: 7px 14px; border: 1px solid rgba(167,139,250,.26); border-radius: 999px;
            background: var(--accent-soft);
        }
        .eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--grad-bright); box-shadow: 0 0 14px var(--glow); }
        .eyebrow .seg-u {
            background: var(--grad-bright); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .eyebrow .seg-o { color: var(--accent-2); }
        h1 { margin: 0 0 14px; font-size: clamp(2.2rem, 6vw, 3.4rem); font-weight: 800; letter-spacing: -.03em; line-height: 1.05; }
        h1 .hl {
            background: var(--grad-bright); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .desc { max-width: 540px; margin: 0 0 38px; color: var(--muted); font-size: 1.08rem; line-height: 1.6; }
        .btns { display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            min-width: 210px; padding: 15px 26px; border-radius: 999px;
            font-weight: 700; font-size: 1rem; text-decoration: none;
            transition: transform .15s, background .2s, border-color .2s, color .2s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-admin { background: var(--grad); color: #ffffff; box-shadow: 0 18px 44px -16px var(--glow); }
        .btn-admin:hover { background: var(--grad-rev); transform: translateY(-2px); }
        .btn-staff { background: transparent; color: var(--accent-2); border: 2px solid var(--border-violet); }
        .btn-staff:hover { border-color: var(--accent-2); background: var(--accent-2-soft); transform: translateY(-2px); }
        .foot { margin-top: 46px; font-size: .82rem; color: var(--muted); }
    </style>
</head>
<body>
    <span class="eyebrow"><span class="dot"></span> Nyaman<span class="seg-u">Super</span><span class="seg-o">App</span></span>

    <h1>Kelola <span class="hl">Penginapan</span><br>dalam satu aplikasi</h1>

    <p class="desc">
        Stok, hunian kamar, dan laporan harian — staff mencatat, admin memantau.
        Semua tersinkron secara langsung dalam satu tempat.
    </p>

    <div class="btns">
        <a href="{{ route('login', ['as' => 'admin']) }}" class="btn btn-admin">Masuk sebagai Admin</a>
        <a href="{{ route('login', ['as' => 'staff']) }}" class="btn btn-staff">Masuk sebagai Staff</a>
    </div>

    <p class="foot">NyamanSuperApp — pilih peran untuk masuk ke panel masing-masing.</p>
</body>
</html>
