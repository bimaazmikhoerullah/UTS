<?php

namespace Tests\Feature;

use App\Messaging\RabbitPublisher;
use App\Models\Outbox;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    private function produkContoh(int $stok = 10): Produk
    {
        return Produk::create([
            'sku' => 'KRD-001',
            'nama' => 'Kerudung Paris Polos',
            'harga' => 45000,
            'stok' => $stok,
        ]);
    }

    private function jual(int $jumlah)
    {
        return $this->postJson('/api/v1/produk/KRD-001/penyesuaian-stok', [
            'perubahan' => -$jumlah,
            'alasan' => 'penjualan',
        ]);
    }

    // --- Bagian 1: pencatatan ke outbox -------------------------------------

    public function test_penjualan_mencatat_satu_pesan_tertunda_di_outbox(): void
    {
        $this->produkContoh(stok: 10);

        $this->jual(3)->assertOk();

        $this->assertSame(1, Outbox::count());
        $pesan = Outbox::first();
        $this->assertSame('stok.penjualan', $pesan->routing_key);
        $this->assertSame('StokDisesuaikan', $pesan->jenis);
        $this->assertSame(7, $pesan->payload['stok_sesudah']);
        $this->assertNull($pesan->dikirim_pada);
    }

    public function test_penjualan_yang_ditolak_tidak_mencatat_apa_pun(): void
    {
        $this->produkContoh(stok: 3);

        $this->jual(5)->assertStatus(409);

        $this->assertSame(0, Outbox::count());
    }

    public function test_bila_outbox_gagal_disimpan_stok_ikut_dibatalkan(): void
    {
        $this->produkContoh(stok: 10);
        Outbox::creating(fn () => throw new RuntimeException('disk penuh'));

        $this->jual(3)->assertStatus(500);

        $this->assertSame(10, Produk::first()->stok); // rollback: tidak ada perubahan setengah jadi
    }
}
