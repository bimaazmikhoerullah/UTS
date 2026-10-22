<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->id();                                           // urutan kirim 
            $table->uuid('message_id')->unique();                   // penolak duplikat
            $table->string('jenis', 60);                            // StokDisesuaikan
            $table->string('routing_key', 60);                      // stok.penjualan
            $table->json('payload');                                // isi event
            $table->timestamp('dikirim_pada')->nullable()->index(); // NULL: belum dikirim
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
