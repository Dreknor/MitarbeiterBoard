#!/usr/bin/env bash

set -e

PROJECT_DIR="$(pwd)"
WEB_USER="www-data"
WEB_GROUP="www-data"

echo "=== Korrigiere Berechtigungen für Laravel Storage in: $PROJECT_DIR ==="

# 1. Benutzer zur Webserver-Gruppe hinzufügen
sudo usermod -aG $WEB_GROUP $USER

# 2. Besitzer & Gruppe speziell für storage und bootstrap/cache anpassen
sudo chown -R $USER:$WEB_GROUP "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# 3. Schreibrechte setzen (775 für Ordner/Dateien in diesen Pfaden)
sudo chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# 4. ACLs setzen: Automatische Vererbung für neu erstellte Dateien/Ordner
sudo setfacl -R -m u:$WEB_USER:rwx,m:rwx "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
sudo setfacl -R -d -m u:$WEB_USER:rwx,m:rwx "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

sudo setfacl -R -m u:$USER:rwx,m:rwx "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
sudo setfacl -R -d -m u:$USER:rwx,m:rwx "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

echo "=== Storage-Berechtigungen erfolgreich gesetzt! ==="
