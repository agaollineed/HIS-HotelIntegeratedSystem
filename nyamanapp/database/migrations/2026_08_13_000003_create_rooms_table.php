<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventaris kamar tetap: 17 King, 3 Twin, 1 Family (total 21).
     * Barisnya dibuat oleh seeder.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique(); // contoh: "101", "202"
            $table->string('tipe');            // king | twin | family
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
