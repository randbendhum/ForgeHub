#!/usr/bin/env bash
# ==============================================================================
# ForgeMod Hub — Production Provisioning & Deploy Script
# Target: Oracle Cloud VM.Standard.E2.1.Micro (1 OCPU, 1 GB RAM) / Ubuntu LTS
# Custom Domain: forgehub.humbertorandon.com (Automatic Let's Encrypt HTTPS)
# ==============================================================================
set -euo pipefail

echo "==> [1/4] Configuring 4 GB Swap file (essential for 1 GB RAM E2.1.Micro)..."
if ! swapon --show | grep -q '/swapfile'; then
    fallocate -l 4G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=4096
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    if ! grep -q '/swapfile' /etc/fstab; then
        echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi
    sysctl vm.swappiness=20
    echo 'vm.swappiness=20' > /etc/sysctl.d/99-forgemod-swap.conf
    echo "    4 GB Swap activated."
else
    echo "    Swap already active."
fi
free -h

echo "==> [2/4] Installing Docker Engine & Docker Compose plugin..."
if ! command -v docker >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y ca-certificates curl gnupg git iptables-persistent
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    . /etc/os-release
    ARCH="$(dpkg --print-architecture)"
    echo "deb [arch=${ARCH} signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" > /etc/apt/sources.list.d/docker.list
    apt-get update -y
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
    usermod -aG docker ubuntu || true
fi

echo "==> [3/4] Opening HTTP (80) and HTTPS (443) in Oracle Ubuntu iptables..."
iptables -C INPUT -p tcp --dport 80 -j ACCEPT 2>/dev/null || iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT
iptables -C INPUT -p tcp --dport 443 -j ACCEPT 2>/dev/null || iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT
iptables -C INPUT -p udp --dport 443 -j ACCEPT 2>/dev/null || iptables -I INPUT 1 -p udp --dport 443 -j ACCEPT
netfilter-persistent save || true

echo "==> [4/4] Base VM provisioning complete!"
docker --version
docker compose version
