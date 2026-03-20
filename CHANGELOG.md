# Changelog

## 1.0.0
- Erste Version der nativen Bitwarden/Vaultwarden-Integration fuer Nextcloud
- Unterstuetzung fuer bitwarden.com (US & EU) und selbst gehostete Instanzen
- Client-seitige Vault-Entschluesselung via Web Crypto API (AES-256-CBC + HMAC-SHA256)
- PBKDF2 und Argon2id Key Derivation (korrekte HKDF-Expand Implementierung)
- Anzeige und Verwaltung aller Cipher-Typen: Login, Sichere Notiz, Karte, Identitaet
- Ordner-Navigation und Volltextsuche
- BruteForceProtection auf Login-Endpunkten
- SSRF-Schutz: nur HTTPS, keine IP-Adressen, keine internen Hostnamen
