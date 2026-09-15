<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel stok / inventory.
     * Kolom `jumlah` dan `updated_at` (last update) inilah yang
     * dibagikan (terintegrasi) antara halaman Staff dan halaman Admin.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('nama');                      // nama stok
            $table->integer('jumlah')->default(0);       // jumlah (diubah oleh staff & admin)
            $table->unsignedBigInteger('harga')->default(0); // harga (Rupiah, diatur admin)
            $table->string('updated_by')->nullable();    // siapa yang terakhir mengubah
            $table->timestamps();                        // created_at + updated_at (= last update)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
