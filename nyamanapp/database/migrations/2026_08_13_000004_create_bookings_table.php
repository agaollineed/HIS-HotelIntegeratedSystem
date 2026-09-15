<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hunian kamar. Satu kamar tidak boleh punya dua booking yang
     * rentang tanggalnya tumpang tindih (divalidasi di controller).
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('nama_tamu');
            $table->string('telepon');
            $table->string('foto_ktp')->nullable();   // path file di disk "public"
            $table->date('check_in');
            $table->unsignedInteger('durasi');        // jumlah malam
            $table->date('check_out');                // = check_in + durasi
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
