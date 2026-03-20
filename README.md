# nc_bitwarden - Native Bitwarden Integration fuer Nextcloud

Nextcloud-App fuer nativen Zugriff auf deinen Bitwarden/Vaultwarden-Tresor.

## Kompatibilitaet
- Nextcloud 31, 32, 33
- PHP 8.1+
- Bitwarden Cloud (US & EU) + Vaultwarden (selbst gehostet)

## Installation

```bash
cp -r nc_bitwarden /path/to/nextcloud/apps/
cd /path/to/nextcloud/apps/nc_bitwarden
npm install && npm run build
sudo -u www-data php /path/to/nextcloud/occ app:enable nc_bitwarden
```

## Konfiguration

1. Nextcloud -> Einstellungen -> Persoenlich -> Bitwarden
2. Server auswaehlen: Cloud US / EU oder Selbst gehostet
3. Bei selbst gehosteter Instanz: Basis-URL eintragen (https://vault.example.com)
4. App in Navigation oeffnen und mit E-Mail + Master-Passwort einloggen

## Fuer lokales Vaultwarden (selbe Netzwerk wie Nextcloud)

```bash
sudo docker exec --user www-data nextcloud-aio-nextcloud \
  php /var/www/html/occ config:system:set allow_local_remote_servers --value=true --type=bool
```

## Sicherheit

- Master-Passwort wird NIEMALS zum Server gesendet
- Nur der abgeleitete Passwort-Hash authentifiziert gegen Bitwarden
- Alle Vault-Items werden im Browser entschluesselt (Web Crypto API)
- Tokens nur in PHP-Session (serverseitig, nie im Browser-Storage)
- Vault-Key nur im Vue-RAM, wird beim Logout/Reload verworfen
- BruteForceProtection auf prelogin + login Endpunkten
- SSRF-Schutz: nur HTTPS-URLs, keine IP-Adressen als Vaultwarden-URL
