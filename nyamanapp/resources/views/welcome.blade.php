{{-- Halaman pembuka: logo Nyaman sebentar, lalu langsung ke halaman login.
     Tidak ada landing page lagi — aplikasi ini hanya untuk pengelola hotel. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NyamanSuperApp</title>
    @include('partials.favicon')
    {{-- Cadangan tanpa JavaScript: tetap sampai di halaman login. --}}
    <noscript><meta http-equiv="refresh" content="2;url={{ route('login') }}"></noscript>
    <link rel="prefetch" href="{{ route('login') }}">
    <link rel="preload" as="image" href="{{ asset('img/logo-senyum.png') }}">
    <style>
        :root { --bg: #0a0710; --muted: #9b93ad; --kuning: #edb652; }
        html { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; overflow: hidden;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 30px;
            background: radial-gradient(760px 480px at 50% 40%, rgba(139,92,246,.22), transparent 64%), var(--bg);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color: var(--muted);
            transition: opacity .35s ease;
        }
        body.keluar { opacity: 0; }

        /* Latar idle yang sama dengan panel: cahaya ungu bergerak pelan. */
        .latar { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .latar span { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .32; }
        .latar .a { width: 480px; height: 480px; right: -160px; top: -170px; background: #7c3aed; animation: melayang-a 26s ease-in-out infinite alternate; }
        .latar .b { width: 420px; height: 420px; left: -170px; bottom: -160px; background: #6d28d9; animation: melayang-b 30s ease-in-out infinite alternate; }
        @keyframes melayang-a { to { transform: translate(-110px, 90px) scale(1.08); } }
        @keyframes melayang-b { to { transform: translate(90px, -80px) scale(1.1); } }

        /* Logo senyum Nyaman langsung di atas latar gelap, dengan cahaya kuning lembut. */
        .logo {
            width: 150px; height: 150px; display: grid; place-items: center;
            filter: drop-shadow(0 18px 40px rgba(237,182,82,.35));
            animation: muncul .7s cubic-bezier(.2,.9,.3,1.25) both, napas 2.6s ease-in-out .7s infinite;
        }
        .logo img { width: 100%; height: auto; display: block; }
        /* Hanya bergerak, tidak memudar: kalau animasi tertahan (mis. tab di
           latar belakang), logonya tetap terlihat. */
        @keyframes muncul { from { transform: scale(.7) translateY(14px); } to { transform: none; } }
        @keyframes napas { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.035); } }

        .muat { width: 148px; height: 4px; border-radius: 999px; background: rgba(255,255,255,.08); overflow: hidden; }
        .muat span {
            display: block; height: 100%; width: 0; border-radius: inherit;
            background: linear-gradient(90deg, #7c3aed, var(--kuning));
            animation: isi 1.5s ease-in-out .2s forwards;
        }
        @keyframes isi { to { width: 100%; } }

        @media (prefers-reduced-motion: reduce) {
            .latar span, .logo, .muat span { animation: none; }
            .muat span { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="latar" aria-hidden="true"><span class="a"></span><span class="b"></span></div>

    <div class="logo"><img src="{{ asset('img/logo-senyum.png') }}" alt="Hotel Nyaman"></div>
    <div class="muat" role="progressbar" aria-label="Memuat"><span></span></div>

    <script>
        (function () {
            const tujuan = @json(route('login'));
            const tenang = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // replace(), bukan pindah biasa: tombol Kembali dari halaman login
            // tidak memutar ulang halaman pembuka.
            setTimeout(function () {
                document.body.classList.add('keluar');
                setTimeout(function () { window.location.replace(tujuan); }, 330);
            }, tenang ? 500 : 1700);
        })();
    </script>
</body>
</html>
