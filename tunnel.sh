#!/usr/bin/env bash
# SSH tunnel: local 3308 → production MySQL 3306
# Run from Git Bash: bash tunnel.sh
KEY=""
for k in ~/.ssh/id_ed25519 ~/.ssh/id_rsa; do
  [ -f "$k" ] && KEY="$k" && break
done
if [ -z "$KEY" ]; then
  echo "ERROR: No SSH key found in ~/.ssh/"; exit 1
fi
echo "Using key: $KEY"
echo "Tunnel: localhost:3308 → 169.58.188.122:3306  (Ctrl+C to stop)"
ssh -i "$KEY" -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -NL 3308:127.0.0.1:3306 root@169.58.188.122
