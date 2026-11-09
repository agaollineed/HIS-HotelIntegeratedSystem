<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\PastikanAktif::class,
        ]);

        // ---------- Cloudflare ----------
        // Bila domain dilewatkan Cloudflare, setiap permintaan datang dari IP
        // Cloudflare, dan IP pengunjung asli dibawa di header X-Forwarded-For.
        // Hanya IP resmi Cloudflare yang dipercaya membawa header itu
        // (https://www.cloudflare.com/ips) — permintaan langsung dari tempat
        // lain tidak bisa memalsukan IP-nya. Selama domain belum lewat
        // Cloudflare, aturan ini tidak berpengaruh apa pun.
        //
        // Yang dijaga: pembatas percobaan login dikunci per email + IP. Tanpa
        // ini, semua staff terbaca memakai beberapa IP Cloudflare yang sama,
        // dan salah ketik satu orang bisa ikut mengunci orang lain.
        $middleware->trustProxies(
            at: [
                '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
                '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
                '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
                '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
                '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
                '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            ],
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT,
        );

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
