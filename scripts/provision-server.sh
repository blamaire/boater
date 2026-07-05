#!/usr/bin/env bash
# Provision-script voor een verse Ubuntu 24.04 (noble) V-Server.
# Voer als root uit, één keer, direct na aanmaak van de server.
#
# gebruikt: Docker Engine + Compose plugin, UFW, fail2ban,
#           unattended-upgrades, en een non-root deploy-user "rzvg"
#           met dezelfde SSH-authorized-keys als root.
#
# Root-SSH-login wordt UITGEZET aan het eind. Test daarom eerst
# `ssh rzvg@<ip>` vanaf een andere terminal voordat je dit script
# als geheel draait; commentarieer anders de laatste sectie uit.

set -euo pipefail

echo "==> Systeem-updates + basispakketten"
apt update && apt upgrade -y
apt install -y curl git ufw fail2ban unattended-upgrades ca-certificates gnupg

echo "==> Automatische security-updates"
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "==> Firewall"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
yes | ufw enable

echo "==> Fail2ban"
systemctl enable --now fail2ban

echo "==> Docker Engine + Compose-plugin"
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo 'deb [arch=amd64 signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu noble stable' \
  > /etc/apt/sources.list.d/docker.list
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker

echo "==> Deploy-user rzvg"
if ! id rzvg >/dev/null 2>&1; then
    useradd -m -s /bin/bash -G docker,sudo rzvg
    install -o rzvg -g rzvg -m 0700 -d /home/rzvg/.ssh
    install -o rzvg -g rzvg -m 0600 ~/.ssh/authorized_keys /home/rzvg/.ssh/authorized_keys
    echo 'rzvg ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/rzvg
    chmod 440 /etc/sudoers.d/rzvg
fi

echo "==> Root-SSH-login uitzetten"
echo "PermitRootLogin no" > /etc/ssh/sshd_config.d/00-disable-root.conf
sshd -t
systemctl restart ssh

echo
echo "=== Provision klaar ==="
docker --version
docker compose version
echo "Test vanaf je lokale machine: ssh rzvg@<server-ip>"
