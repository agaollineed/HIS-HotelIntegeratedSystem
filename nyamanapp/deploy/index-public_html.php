<?php

/**
 * index.php untuk diletakkan di ~/public_html
 * ------------------------------------------------------------------
 * HANYA dipakai bila document root domain TIDAK bisa diarahkan ke
 * folder public/ aplikasi (umum di shared hosting yang membatasi).
 *
 * Susunan yang diasumsikan:
 *   /home/USERCPANEL/nyaman-app/     <- seluruh aplikasi Laravel (di LUAR public_html)
 *   /home/USERCPANEL/public_html/    <- isi folder public/ disalin ke sini
 *
 * Ganti $app di bawah bila nama foldernya berbeda.
 */

$app = __DIR__ . '/../nyaman-app';

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = $app . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app . '/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $laravel */
$laravel = require_once $app . '/bootstrap/app.php';

$laravel->handleRequest(Illuminate\Http\Request::capture());
