#!/bin/bash

# rabit.sh - Controller on/off RabbitMQ Docker Container
# Credentials: User=xavyera | Pass=bima

CONTAINER_NAME="rabbitmq"
IMAGE_NAME="rabbitmq:4-management"
RABBIT_USER="xavyera"
RABBIT_PASS="bima"

start_rabbitmq() {
    echo "=========================================="
    echo "  Menyalakan RabbitMQ..."
    echo "=========================================="

    # Cek apakah container sudah ada (baik running maupun stopped)
    if [ "$(docker ps -aq -f name=^/${CONTAINER_NAME}$)" ]; then
        # Cek apakah sudah running
        if [ "$(docker ps -q -f name=^/${CONTAINER_NAME}$)" ]; then
            echo "[INFO] Container '${CONTAINER_NAME}' sudah berjalan."
        else
            echo "[INFO] Menjalankan container '${CONTAINER_NAME}' yang sudah ada..."
            docker start ${CONTAINER_NAME}
        fi
    else
        echo "[INFO] Membuat dan menjalankan container '${CONTAINER_NAME}' baru..."
        docker run -d \
            --name ${CONTAINER_NAME} \
            -p 5672:5672 \
            -p 15672:15672 \
            -e RABBITMQ_DEFAULT_USER=${RABBIT_USER} \
            -e RABBITMQ_DEFAULT_PASS=${RABBIT_PASS} \
            ${IMAGE_NAME}
    fi

    echo ""
    echo "Status RabbitMQ:"
    echo "- AMQP Port     : 5672"
    echo "- Management UI : http://localhost:15672"
    echo "- Username      : ${RABBIT_USER}"
    echo "- Password      : ${RABBIT_PASS}"
    echo "=========================================="
}

stop_rabbitmq() {
    echo "=========================================="
    echo "  Menghentikan RabbitMQ..."
    echo "=========================================="

    if [ "$(docker ps -q -f name=^/${CONTAINER_NAME}$)" ]; then
        docker stop ${CONTAINER_NAME}
        echo "[OK] Container '${CONTAINER_NAME}' berhasil dihentikan."
    elif [ "$(docker ps -aq -f name=^/${CONTAINER_NAME}$)" ]; then
        echo "[INFO] Container '${CONTAINER_NAME}' memang sudah dalam kondisi mati (stopped)."
    else
        echo "[INFO] Container '${CONTAINER_NAME}' tidak ditemukan."
    fi
    echo "=========================================="
}

# Cek argumen CLI (misal: ./rabit.sh on atau ./rabit.sh 1)
CHOICE="$1"

if [ -z "$CHOICE" ]; then
    echo "=========================================="
    echo "   RABBITMQ CONTROLLER (Docker)"
    echo "=========================================="
    echo "1. On   (Jalankan RabbitMQ)"
    echo "2. Stop (Matikan RabbitMQ)"
    echo "=========================================="
    read -p "Pilih menu [1/2]: " CHOICE
fi

case "$CHOICE" in
    1|[oO][nN]|[sS][tT][aA][rR][tT])
        start_rabbitmq
        ;;
    2|[sS][tT][oO][pP])
        stop_rabbitmq
        ;;
    *)
        echo "[ERROR] Pilihan tidak valid. Gunakan 1 (on) atau 2 (stop)."
        exit 1
        ;;
esac
