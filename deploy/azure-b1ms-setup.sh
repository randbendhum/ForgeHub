#!/usr/bin/env bash
# ==============================================================================
# ForgeMod Hub — Script de Provisionamento e Deploy para Azure B1ms (Ubuntu LTS)
# Especificação alvo: Standard_B1ms (1 vCPU, 2 GiB RAM) + Domínio Próprio
# Uso: sudo bash deploy/azure-b1ms-setup.sh teu-dominio.com
# ==============================================================================
set -euo pipefail

DOMAIN="${1:-}"
if [[ -z "$DOMAIN" ]]; then
    echo "ERRO: Indica o teu domínio próprio."
    echo "Uso: sudo bash deploy/azure-b1ms-setup.sh forgemod.teudominio.com"
    exit 1
fi

echo "==> [1/6] A configurar 2 GiB de Swap (essencial para Azure B1ms com 2 GiB RAM)..."
if ! swapon --show | grep -q '/swapfile'; then
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    if ! grep -q '/swapfile' /etc/fstab; then
        echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi
    sysctl vm.swappiness=10
    echo 'vm.swappiness=10' > /etc/sysctl.d/99-forgemod-swap.conf
    echo "    Swap de 2 GiB ativo."
else
    echo "    Swap já está configurado."
fi

echo "==> [2/6] A verificar instalação do Docker e Docker Compose..."
if ! command -v docker >/dev/null 2>&1; then
    apt-get update -y
    apt-get install -y ca-certificates curl gnupg ufw
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" > /etc/apt/sources.list.d/docker.list
    apt-get update -y
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
fi

echo "==> [3/6] A configurar Firewall UFW (Portas 22, 80, 443)..."
if command -v ufw >/dev/null 2>&1; then
    ufw allow OpenSSH >/dev/null 2>&1 || ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow 443/udp
    ufw --force enable
fi

echo "==> [4/6] A preparar variáveis de ambiente (.env) para https://${DOMAIN}..."
if [[ ! -f .env ]]; then
    cp .env.example .env
fi

DB_PASS="$(openssl rand -hex 16)"
DB_ROOT_PASS="$(openssl rand -hex 16)"
APP_KEY_VAL="base64:$(openssl rand -base64 32)"

sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|^DOMAIN_NAME=.*|DOMAIN_NAME=${DOMAIN}|" .env
sed -i "s|^FORCE_HTTPS=.*|FORCE_HTTPS=true|" .env

if grep -q "^APP_KEY=$" .env; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VAL}|" .env
fi
if grep -q "^DB_PASSWORD=secret" .env; then
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
    sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASS}|" .env
fi

echo "==> [5/6] A construir e iniciar os containers de produção..."
docker compose -f docker-compose.prod.yml up -d --build

echo "==> [6/6] Concluído!"
echo "----------------------------------------------------------------------"
echo " ForgeMod Hub ativo em: https://${DOMAIN}"
echo " Certifica-te de que no Azure Portal (Network Security Group da B1ms)"
echo " as portas TCP 80 e 443 estão abertas e que o registo DNS 'A' de"
echo " ${DOMAIN} aponta para o IP Público desta VM Azure."
echo "----------------------------------------------------------------------"

