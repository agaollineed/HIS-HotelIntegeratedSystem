<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_logs', function (Blueprint $table) {
            $table->id();
            // Referensi ke stok. Kalau stok dihapus, log tetap tersimpan (stock_id jadi null).
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nama');                       // snapshot nama stok saat itu
            $table->string('action');                     // create | update | delete | increment | decrement
            $table->text('keterangan');                   // detail perubahan (dapat dibaca manusia)
            $table->integer('selisih')->nullable();       // perubahan jumlah (+/-), null untuk non-jumlah
            $table->string('by_name');                    // siapa pelakunya
            $table->string('by_role')->default('-');      // admin | staff
            $table->timestamps();                         // created_at = kapan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_logs');
    }
};
