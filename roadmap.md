# 🗺️ Project Development Roadmap — Warung Pak Hendy

Dokumen ini mencatat tahapan pengembangan, daftar tugas (_todo list_), dan target _milestone_ untuk proyek **Sistem Manajemen Operasional & Kasir Digital Warung Pak Hendy** (Kelompok 67).

## 📍 Ringkasan Tahapan Proyek

```
[ Phase 1: Checkpoint 1 ] ──► [ Phase 2: Design & Setup ] ──► [ Phase 3: Implementation ] ──► [ Phase 4: Integration & Testing ]
      (COMPLETED)                     (IN PROGRESS)                  (NEXT UP)                          (UPCOMING)
```

## 📌 Status & Milestones

### 🟢 Phase 1: Analisis Kebutuhan & Desain Arsitektur (Checkpoint 1)

- [ ] **Mitra & Landscape:** Identifikasi proses bisnis dan 4 komponen utama Warung Pak Hendy.
    
- [ ] **Identifikasi Persoalan:** Analisis risiko _data silo_ dan penetapan data yang perlu diintegrasikan.
    
- [ ] **Pemilihan Gaya Integrasi:** Evaluasi gaya integrasi (REST API untuk POS-Backend, RabbitMQ untuk Backend-Supplier).
    
- [ ] **Penyusunan Dokumentasi:** Pembuatan dokumen `api.md`, `architecture.md`, `broker.md`, `contracts.md`, `flow.md`, dan `messaging.md`.
    

### 🟡 Phase 2: Lingkungan Pengembangan & Spesifikasi Kontrak (Checkpoint 2)

- [ ] **Infrastruktur Lokal:**
    
    - [ ] Setup Docker & Laravel Sail untuk `inventory-backend`.
        
    - [ ] Setup container PostgreSQL dan RabbitMQ 4.x.
        
- [ ] **Kontrak API & OpenAPI:**
    
    - [ ] Membuat file `openapi.yaml` lengkap berbasis OpenAPI 3.1.
        
    - [ ] Menjalankan linting menggunakan **Spectral** untuk memastikan kepatuhan standar.
        
- [ ] **Setup Topologi Message Broker:**
    
    - [ ] Menyiapkan skrip inisialisasi RabbitMQ untuk membuat Exchange (`inventory.events`, `inventory.dlx`) dan Queue (`supplier.orders.queue`, `supplier.orders.dlq`).
        

### 🔵 Phase 3: Implementasi Layanan (Checkpoint 3)

- [ ] **Inventory Backend Service (Laravel):**
    
    - [ ] Implementasi _Database Migrations_ & _Models_ (Produk, Transaksi, TransaksiItem).
        
    - [ ] Implementasi `FormRequest` validasi dan `ProdukResource`.
        
    - [ ] Menangani pemotongan stok dengan _Pessimistic Locking_ (`lockForUpdate`).
        
    - [ ] Menangani kustom exception handler berbasis RFC 9457 `application/problem+json`.
        
    - [ ] Implementasi publisher RabbitMQ menggunakan `php-amqplib` untuk event `StockThresholdReached`.
        
- [ ] **Supplier Order Service (Consumer):**
    
    - [ ] Membuat CLI Consumer (`php artisan supplier:consume`).
        
    - [ ] Implementasi penanganan idempoten berbasis `event_id` unik.
        
    - [ ] Implementasi konfirmasi sinyal `ACK` / `NACK` (ke DLQ).
        

### 🔴 Phase 4: Integration Testing & Finalization (Checkpoint 4 / Final)

- [ ] **Pengujian Otomatis (TDD / Feature Tests):**
    
    - [ ] Test `GET /v1/produk` (Daftar & Detail).
        
    - [ ] Test `POST /v1/transaksi` berhasil (201 Created).
        
    - [ ] Test `POST /v1/transaksi` galat stok kurang (409 Conflict).
        
    - [ ] Test pengiriman event ke RabbitMQ saat stok menyentuh batas minimum.
        
- [ ] **Pengujian End-to-End (E2E):**
    
    - [ ] Simulasi transaksi Kasir POS -> Pemotongan Stok -> Trigger Restock -> Consumer Supplier.
        
    - [ ] Simulasi kegagalan jaringan & pengujian _Dead Letter Queue_ (DLQ).
        
- [ ] **Finalisasi Laporan:**
    
    - [ ] Penyusunan Laporan Proyek Akhir dan Dokumentasi Lengkap.
        

## 🛠️ Jadwal Tugas Kelompok (Kelompok 67)

|                          |                                                                         |                    |
| ------------------------ | ----------------------------------------------------------------------- | ------------------ |
| **Anggota Kelompok**     | **Fokus Tanggung Jawab**                                                | **Target Selesai** |
| **Difki Junaedi**        | Infrastructure Setup (Docker, RabbitMQ), Backend Core & Event Publisher | Phase 2 & 3        |
| **Nurul Hidayah**        | REST API Controller, Validasi RFC 9457, Feature Testing (TDD)           | Phase 2 & 3        |
| **Bima Azmi Khoerullah** | Supplier Order Service (Consumer Handler, DLQ, Idempotency Logic)       | Phase 3 & 4        |