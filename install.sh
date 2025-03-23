#!/bin/bash

#!/bin/bash

CONFIG_FILE=".config_completed"

if [ -f "$CONFIG_FILE" ]; then
  echo "Skript wurde bereits ausgefuehrt. Beende."
  exit 1
fi

# Kopiere .env.example nach .env, falls .env nicht existiert
if [ ! -f .env ]; then
  cp .env.example .env
  echo ".env aus .env.example erstellt."
fi


# Funktion zum Abfragen einer Variablen und Speichern in .env
ask_and_replace() {
  read -p "Bitte $1 eingeben: " value
  if grep -q "^$2=" .env; then
    # Variable existiert bereits, ersetze sie
    sed -i "s/^$2=.*/$2=$value/" .env
  else
    # Variable existiert nicht, füge sie hinzu
    echo "$2=$value" >> .env
  fi
}

# Variablen abfragen
echo "Bitte geben Sie die folgenden Variablen ein:"
ask_and_replace "App-Url" APP_URL
ask_and_replace "Datenbank-Host" DB_HOST
ask_and_replace "Datenbank-Port" DB_PORT
ask_and_replace "Datenbank-Name" DB_DATABASE
ask_and_replace "Datenbank-Benutzer" DB_USERNAME
ask_and_replace "Datenbank-Passwort" DB_PASSWORD

ask_and_replace "Mail-Host" MAIL_HOST
ask_and_replace "Mail-Port" MAIL_PORT
ask_and_replace "Mail-Benutzername" MAIL_USERNAME
ask_and_replace "Mail-Passwort" MAIL_PASSWORD
ask_and_replace "Mail-Verschlüsselung" MAIL_ENCRYPTION
ask_and_replace "Mail-Absender-Adresse" MAIL_FROM_ADDRESS
ask_and_replace "Mail-Absender-Name" MAIL_FROM_NAME





# Befehle ausführen (Beispiele)
echo "Führe composer install aus..."
composer install

echo "Führe php artisan migrate aus..."
php artisan migrate

echo "Führe php artisan db:seed aus..."
php artisan db:seed

echo "Erstelle AnwednungsKey..."
php artisan key:generate
php artisan webpush:vapid

echo "Führe php artisan storage:link aus..."
php artisan storage:link

echo "Es müssen noch die folgenden Aufgaben manuell ausgeführt werden:"
echo "1. Einstellungen in .env anpassen"
echo "2. Den Cronjob erstellen. Die erfolgt den Befehl: crontab -e"
echo "3. Den folgenden Befehl in die Crontab einfügen: * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1"

touch "$CONFIG_FILE"

echo "Fertig!"
