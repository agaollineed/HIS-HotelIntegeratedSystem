@php
    // Hanya untuk judul halaman — TIDAK ada kredensial apa pun di sisi klien.
    $as    = request('as');
    $peran = ['admin' => 'Admin', 'staff' => 'Staff'][$as] ?? null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — NyamanSuperApp</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0710; --bg-2: #100b1a; --surface: #16101f; --surface-2: #1d1529;
            --accent: #a78bfa; --accent-600: #8b5cf6;
            --grad: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
            --grad-bright: linear-gradient(135deg, #a78bfa 0%, #7c3aed 100%);
            --grad-rev: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
            --accent-soft: rgba(167,139,250,.13);
            --accent-2: #d8b4fe; --accent-2-600: #c084fc;
            --accent-2-glow: rgba(216, 180, 254, .30);
            --border-violet: rgba(216, 180, 254, .28);
            --accent-2-soft: rgba(216, 180, 254, .12);
            --glow: rgba(139,92,246,.45); --text: #f6f4fb; --muted: #9b93ad;
            --border: rgba(255,255,255,.09);
            --danger: #fb7185; --danger-soft: rgba(251,113,133,.12); --danger-line: rgba(251,113,133,.32);
        }
        html { color-scheme: dark; }
        * { box-sizing: border-box; }
@include('partials.icon-css')
        body {
            margin: 0; min-height: 100vh; color: var(--text);
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            display: flex; align-items: center; justify-content: center; padding: 24px;
            background:
                radial-gradient(760px 480px at 50% -10%, rgba(139,92,246,.26), transparent 62%),
                radial-gradient(620px 420px at -10% 110%, rgba(109,40,217,.20), transparent 58%),
                var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%; max-width: 400px;
            background: linear-gradient(180deg, var(--surface), var(--bg-2));
            border: 1px solid var(--border); border-radius: 20px; padding: 32px;
            box-shadow: 0 34px 80px -34px rgba(0,0,0,.95);
        }
        .brand { display: flex; align-items: center; gap: 9px; font-weight: 800; margin-bottom: 6px; }
        .brand .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--grad-bright); box-shadow: 0 0 14px var(--glow); }
        .brand .sub {
            background: var(--grad-bright); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .brand .sub2 { color: var(--accent-2); }
        h1 { margin: 18px 0 4px; font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; }
        .lead { margin: 0 0 22px; color: var(--muted); font-size: .92rem; }

        .hint {
            display: flex; gap: 10px; align-items: flex-start;
            background: var(--accent-soft); border: 1px solid rgba(167,139,250,.26);
            border-radius: 12px; padding: 12px 14px; margin-bottom: 22px; font-size: .82rem; color: var(--accent);
        }
        .hint code { font-variant-numeric: tabular-nums; color: var(--accent-2); }

        .err { background: var(--danger-soft); border: 1px solid var(--danger-line); color: var(--danger);
               border-radius: 12px; padding: 11px 14px; margin-bottom: 18px; font-size: .88rem; }

        .fld { margin-bottom: 16px; }
        .fld label { display: block; font-size: .74rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
        .fld input[type=email], .fld input[type=password] {
            width: 100%; font-family: inherit; font-size: 1rem; color: var(--text);
            background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px;
            color-scheme: dark;
        }
        .fld input:focus { outline: none; border-color: var(--accent); }
        .remember { display: flex; align-items: center; gap: 8px; font-size: .88rem; color: var(--muted); margin-bottom: 22px; }
        .remember input { accent-color: var(--accent); width: 15px; height: 15px; }

        .btn {
            width: 100%; font-family: inherit; font-weight: 700; font-size: 1rem; cursor: pointer;
            background: var(--grad); color: #ffffff; border: none; border-radius: 999px; padding: 13px;
            box-shadow: 0 14px 32px -16px var(--glow);
            transition: background .2s;
        }
        .btn:hover { background: var(--grad-rev); }

        /* Latar idle: cahaya ungu yang melayang pelan, sama dengan panel. */
        .latar { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .latar span { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .32; will-change: transform; }
        .latar .a { width: 480px; height: 480px; right: -160px; top: -170px; background: #7c3aed; animation: melayang-a 26s ease-in-out infinite alternate; }
        .latar .b { width: 420px; height: 420px; left: -170px; bottom: -160px; background: #6d28d9; animation: melayang-b 30s ease-in-out infinite alternate; }
        .latar .c { width: 260px; height: 260px; left: 45%; top: 40%; background: #a78bfa; opacity: .12; animation: melayang-c 34s ease-in-out infinite alternate; }
        @keyframes melayang-a { to { transform: translate(-110px, 90px) scale(1.08); } }
        @keyframes melayang-b { to { transform: translate(90px, -80px) scale(1.1); } }
        @keyframes melayang-c { to { transform: translate(-80px, -60px) scale(1.15); } }

        /* Kartu masuk perlahan setelah halaman pembuka. */
        .card { animation: masuk .55s cubic-bezier(.2,.8,.3,1) both; }
        @keyframes masuk { from { transform: translateY(16px) scale(.98); } to { transform: none; } }

        /* Tangan melambai — sama dengan sapaan di Beranda. */
        .lambai { display: inline-block; transform-origin: 70% 80%; animation: lambai 3.2s ease-in-out infinite; }
        @keyframes lambai {
            0%, 60%, 100% { transform: rotate(0); }
            65% { transform: rotate(16deg); } 70% { transform: rotate(-8deg); }
            75% { transform: rotate(14deg); } 80% { transform: rotate(-4deg); } 85% { transform: rotate(10deg); }
        }

        @media (prefers-reduced-motion: reduce) {
            .latar span, .card, .lambai { animation: none; }
        }
    </style>
</head>
<body>
    <div class="latar" aria-hidden="true"><span class="a"></span><span class="b"></span><span class="c"></span></div>

    <div class="card">
        <div class="brand"><span class="dot"></span> Nyaman<span class="sub">Super</span><span class="sub2">App</span></div>

        <h1>Selamat datang <span class="lambai" aria-hidden="true">👋🏻</span></h1>
        <p class="lead">Masuk{{ $peran ? ' sebagai ' . $peran : '' }} dengan email dan kata sandi Anda.</p>

        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="fld">
                <label for="email">Email</label>
                <input id="email" type="email" name="email"
                       value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="fld">
                <label for="password">Kata Sandi</label>
                <input id="password" type="password" name="password"
                       required autocomplete="current-password">
            </div>

            <label class="remember">
                <input type="checkbox" name="remember"> Ingat saya
            </label>

            <button type="submit" class="btn">Masuk</button>
        </form>
    </div>
</body>
</html>
