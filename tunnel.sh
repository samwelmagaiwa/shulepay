#!/usr/bin/env bash
# Usage: bash tunnel.sh
ssh -i "C:/Users/MAMC/.ssh/id_ed25519" -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -NL 3308:127.0.0.1:3306 root@169.58.188.122
