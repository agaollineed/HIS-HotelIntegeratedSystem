<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — NyamanSuperApp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* ---------- Dasar: hitam ---------- */
            --bg:          #0a0710;                       /* hitam ber-tint ungu */
            --bg-2:        #100b1a;
            --surface:     #16101f;                       /* permukaan kartu */
            --surface-2:   #1d1529;
            --surface-3:   #251c34;                       /* elevasi tertinggi */

            /* ---------- Ungu (gradien) ---------- */
            --accent:      #a78bfa;                       /* ungu utama di atas gelap */
            --accent-600:  #8b5cf6;
            --accent-700:  #7c3aed;
            --accent-soft: rgba(167, 139, 250, .13);      /* isian chip/badge */
            --accent-glow: rgba(139, 92, 246, .45);
            --grad:        linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);   /* teks putih: kontras 5.7 */
            --grad-bright: linear-gradient(135deg, #a78bfa 0%, #7c3aed 100%); /* dekoratif (teks/dot) */
            --grad-rev:    linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
            --grad-bar:    linear-gradient(180deg, #a78bfa 0%, #6d28d9 100%);
            --grad-surface: linear-gradient(180deg, rgba(167,139,250,.07), rgba(167,139,250,.02));

            /* Aksen sekunder: ungu muda (menggantikan oranye) */
            --accent-2:      #d8b4fe;
            --accent-2-600:  #c084fc;
            --accent-2-soft: rgba(216, 180, 254, .12);
            --accent-2-glow: rgba(216, 180, 254, .30);
            --border-violet: rgba(216, 180, 254, .28);

            /* ---------- Putih & garis ---------- */
            --text:        #f6f4fb;                       /* putih */
            --muted:       #9b93ad;
            --border:      rgba(167, 139, 250, .26);      /* garis ungu */
            --border-2:    rgba(255, 255, 255, .09);      /* garis netral */
            --hover:       rgba(167, 139, 250, .07);
            --overlay:     rgba(4, 2, 8, .74);

            /* ---------- Warna fungsional ---------- */
            --danger:      #fb7185;
            --danger-soft: rgba(251, 113, 133, .12);
            --danger-line: rgba(251, 113, 133, .32);
            --alert:       #f87171;                       /* penanda stok habis */
            --alert-soft:  rgba(248, 113, 113, .12);
            --alert-line:  rgba(248, 113, 113, .30);
            --ok:          #4ade80;
            /* Isian & garis untuk hijau — sebelumnya hanya ada warna tintanya,
               sehingga setiap chip "beres" terpaksa menuliskan rgba sendiri
               dan perlahan menjadi enam hijau yang sedikit berbeda. */
            --ok-soft:     rgba(74, 222, 128, .12);
            --ok-line:     rgba(74, 222, 128, .30);

            /* Amber: "perlu perhatian" — DP yang belum lunas, selisih setoran
               yang belum dijelaskan. Bukan merah (itu untuk masalah) dan bukan
               hijau (itu untuk yang sudah beres). */
            --warn:        #fbbf24;
            --warn-soft:   rgba(251, 191, 36, .12);
            --warn-line:   rgba(251, 191, 36, .30);
            --info:        #7dd3fc;
            --info-soft:   rgba(125, 211, 252, .12);
            --info-line:   rgba(125, 211, 252, .28);

            --radius:      14px;
        }

        * { box-sizing: border-box; }

        html { color-scheme: dark; }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(900px 520px at 100% -10%, rgba(139,92,246,.22), transparent 62%),
                radial-gradient(760px 520px at -10% 110%, rgba(109,40,217,.20), transparent 60%),
                var(--bg);
            background-attachment: fixed;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

@include('partials.icon-css')
        .btn .ic { opacity: .95; }

        .mono { font-variant-numeric: tabular-nums; }

        /* ---------- Top bar ---------- */
        .topbar {
            position: sticky; top: 0; z-index: 20;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; padding: 14px 22px;
            background: rgba(10, 7, 16, .78);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border-2);
        }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; letter-spacing: -.02em; }
        .brand a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .brand .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--grad-bright); box-shadow: 0 0 16px var(--accent-glow); }
                .brand .sub {
            background: var(--grad-bright); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .brand .sub2 { color: var(--accent-2); }
        .brand .word { display: inline-flex; gap: 4px; }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .topbar-right { display: flex; align-items: center; gap: 14px; }
        .btn-home { flex: 0 0 auto; }
        .who { display: flex; flex-direction: row; align-items: center; gap: 10px; line-height: 1.15; }
        .who .name { font-weight: 600; font-size: .92rem; }
        .badge {
            font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            padding: 3px 9px; border-radius: 999px;
            color: var(--accent); background: var(--accent-soft); border: 1px solid var(--border);
        }
        /* Superadmin ditandai berbeda: peran ini memegang penghapusan data,
           kunci akun, dan angka keuangan — pemakainya sebaiknya selalu sadar
           sedang memakainya. */
        .badge.super { color: var(--text); background: var(--grad); border-color: transparent; }
        .btn-logout {
            font-family: inherit; cursor: pointer;
            background: transparent; color: var(--muted);
            border: 1px solid var(--border-2); border-radius: 999px;
            padding: 8px 16px; font-weight: 600; font-size: .85rem;
            transition: .2s;
        }
        .btn-logout:hover { color: var(--text); border-color: var(--danger); }

        /* ---------- Layout ---------- */
        .wrap { max-width: 1040px; margin: 0 auto; padding: 34px 22px 80px; }
        .page-head { margin-bottom: 26px; }
        .page-head h1 { margin: 0 0 6px; font-size: 1.75rem; font-weight: 800; letter-spacing: -.025em; }
        .page-head p { margin: 0; color: var(--muted); font-size: .96rem; }

        /* ---------- Flash ---------- */
        .flash {
            display: flex; align-items: center; gap: 10px;
            border-radius: var(--radius); padding: 13px 16px; margin-bottom: 22px;
            font-size: .92rem; font-weight: 500;
        }
        .flash.ok  { background: var(--accent-soft); border: 1px solid var(--border);      color: var(--accent); }
        .flash.err { background: var(--danger-soft); border: 1px solid var(--danger-line); color: var(--danger); }
        .flash .flash-ic { flex: 0 0 auto; }

        /* ---------- Buttons ---------- */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            font-family: inherit; font-weight: 700; font-size: .92rem; cursor: pointer;
            border-radius: 999px; padding: 11px 20px; text-decoration: none; border: none;
            transition: transform .15s, background .2s, border-color .2s, color .2s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-accent {
            background: var(--grad); color: #ffffff;
            box-shadow: 0 12px 28px -14px var(--accent-glow);
        }
        .btn-accent:hover { background: var(--grad-rev); transform: translateY(-2px); box-shadow: 0 16px 32px -14px var(--accent-glow); }
        .btn-ghost  { background: rgba(255,255,255,.03); color: var(--text); border: 1px solid var(--border-2); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--hover); }
        .btn-sm { padding: 8px 14px; font-size: .82rem; }
        .btn-danger { background: transparent; color: var(--danger); border: 1px solid var(--danger-line); }
        .btn-danger:hover { background: var(--danger-soft); }
        /* Aksi sekunder yang perlu menonjol (mis. Tamu Check-in) */
        .btn-orange {
            background: transparent; color: var(--accent-2);
            border: 1px solid var(--border-violet); box-shadow: 0 10px 24px -16px var(--accent-2-glow);
        }
        .btn-orange:hover { background: var(--accent-2-soft); border-color: var(--accent-2); transform: translateY(-2px); }

        /* ---------- Card ---------- */
        .card {
            background: linear-gradient(180deg, var(--surface), var(--bg-2));
            border: 1px solid var(--border-2); border-radius: 18px;
            box-shadow: 0 24px 60px -34px rgba(0,0,0,.9);
        }

        a { color: var(--accent); }

        /* ---------- Lightbox foto KTP ---------- */
        .ktp-thumb {
            width: 58px; height: 40px; object-fit: cover; border-radius: 8px; cursor: zoom-in;
            border: 1px solid var(--border-2); display: block;
            transition: transform .15s, box-shadow .15s;
        }
        .ktp-thumb:hover { transform: scale(1.05); box-shadow: 0 8px 20px -8px var(--accent-glow); }
        .lightbox {
            position: fixed; inset: 0; z-index: 60;
            display: flex; align-items: center; justify-content: center; padding: 24px;
            background: var(--overlay); backdrop-filter: blur(6px); cursor: zoom-out;
        }
        .lightbox[hidden] { display: none !important; }
        .lightbox img {
            max-width: min(92vw, 900px); max-height: 85vh;
            border-radius: 14px; background: var(--surface-3);
            border: 1px solid var(--border-2);
            box-shadow: 0 50px 110px -30px rgba(0,0,0,.95);
        }
        .lightbox .lb-close {
            position: absolute; top: 18px; right: 22px;
            width: 40px; height: 40px; border-radius: 50%; border: none; cursor: pointer;
            display: grid; place-items: center;
            background: var(--surface-3); color: var(--text);
            border: 1px solid var(--border-2);
        }
        .lightbox .lb-close:hover { border-color: var(--accent); color: var(--accent); }

        @media (max-width: 560px) {
            .who .name { display: none; }
            .page-head h1 { font-size: 1.45rem; }
        }
    </style>
    @stack('head')
</head>
<body>
    <header class="topbar">
        <div class="topbar-left">
            <div class="brand">
                <a href="{{ route('dashboard') }}">
                    <span class="dot"></span>
                    <span class="word">Nyaman<span class="sub">Super</span><span class="sub2">App</span></span>
                </a>
            </div>
            @unless (request()->routeIs('dashboard'))
                <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm btn-home">@include('partials.icon', ['name' => 'arrow-left', 'class' => 'ic-xs']) Beranda</a>
            @endunless
        </div>
        <div class="topbar-right">
            <div class="who">
                <span class="name">{{ Auth::user()->name }}</span>
                <span class="badge {{ Auth::user()->isSuperadmin() ? 'super' : '' }}">{{ Auth::user()->labelRole() }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">Keluar</button>
            </form>
        </div>
    </header>

    <main class="wrap">
        @if (session('success'))
            <div class="flash ok">@include('partials.icon', ['name' => 'check', 'class' => 'flash-ic']) {{ session('success') }}</div>
        @endif
        {{-- Pesan gagal yang dikirim lewat with('error') — mis. pengajuan
             perubahan yang tidak bisa diterapkan. Tanpa blok ini, pesannya
             hilang tanpa jejak dan layar terlihat seolah tidak terjadi apa-apa. --}}
        @if (session('error'))
            <div class="flash err">@include('partials.icon', ['name' => 'alert', 'class' => 'flash-ic']) {{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash err">@include('partials.icon', ['name' => 'alert', 'class' => 'flash-ic']) {{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>

    <div class="lightbox" id="lightbox" hidden>
        <img src="" alt="Foto KTP">
        <button type="button" class="lb-close" title="Tutup">@include('partials.icon', ['name' => 'x'])</button>
    </div>
    <script>
        // Lightbox sederhana: elemen dengan [data-lightbox] membuka fotonya besar.
        (function () {
            const lb = document.getElementById('lightbox');
            if (!lb) return;
            const img = lb.querySelector('img');

            document.addEventListener('click', function (e) {
                const trigger = e.target.closest('[data-lightbox]');
                if (trigger) {
                    e.preventDefault();
                    img.src = trigger.getAttribute('data-lightbox');
                    lb.hidden = false;
                    return;
                }
                if (!lb.hidden && e.target.closest('#lightbox')) {
                    lb.hidden = true;
                    img.src = '';
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !lb.hidden) {
                    lb.hidden = true;
                    img.src = '';
                }
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
