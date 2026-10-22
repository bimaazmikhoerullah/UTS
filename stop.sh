#!/bin/bash

# stop.sh - Matikan semua container Laravel Sail dan bebasin port 80

echo "🛑 Menghentikan container Laravel Sail..."

# Pastikan ada file sail (vendor/bin/sail) atau gunakan docker compose langsung
if [ -f vendor/bin/sail ]; then
    ./vendor/bin/sail down
elif [ -f docker-compose.yml ]; then
    docker compose down
else
    echo "❌ vendor/bin/sail atau docker-compose.yml tidak ditemukan!"
    exit 1
fi

echo ""
echo "✅ Semua container Laravel Sail sudah dihentikan."

# Opsional: bebasin port 80 kalau masih ada proses yang nyebelin (Apache/Nginx)
PORT80=$(sudo lsof -t -i:80 2>/dev/null)
if [ -n "$PORT80" ]; then
    echo ""
    echo "⚠️  Port 80 masih digunakan oleh proses PID: $PORT80"
    read -p "Apakah ingin memaksa matikan proses pada port 80? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sudo kill -9 $PORT80
        echo "✅ Port 80 sudah dibebaskan."
    else
        echo "ℹ️  Port 80 dibiarkan tetap digunakan."
    fi
else
    echo "ℹ️  Port 80 sudah bebas, tidak ada proses yang menggunakan."
fi

echo ""
echo "👋 Selesai. Untuk nyalakan lagi, jalankan: sail up -d"
