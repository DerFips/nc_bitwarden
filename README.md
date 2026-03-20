# 🔐 nc_bitwarden

> Native Bitwarden & Vaultwarden Integration for Nextcloud

![Nextcloud](https://img.shields.io/badge/Nextcloud-31--33-0082C9?logo=nextcloud&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-AGPL--3.0-green)
![Security](https://img.shields.io/badge/E2E_Encrypted-✓-brightgreen)

Access your Bitwarden vault directly from Nextcloud – no browser extension required,
no data shared with third parties. All decryption happens **client-side in the browser**.
Your master password never leaves your device.

---

## ✨ Features

- 🔑 **Login entries** – username, password, TOTP, URLs
- 📝 **Secure notes** – encrypted free-text notes
- 💳 **Credit cards** – card number, CVV, expiry date
- 🪪 **Identities** – address, phone, email, company
- 📁 **Folder navigation** with entry count badges
- 🔍 **Full-text search** across all vault items
- ➕ **Create & edit** vault entries
- 🏢 **Organisation vaults** (shared vaults via RSA-OAEP)
- 🌍 **Bitwarden Cloud** (US & EU) + **self-hosted Vaultwarden**

---

## 🔒 Security Architecture

```
Browser                          Nextcloud Server              Bitwarden / Vaultwarden
   │                                   │                               │
   │── Master Password ──▶ PBKDF2/     │                               │
   │                        Argon2id   │                               │
   │                          │        │                               │
   │                          ▼        │                               │
   │                     Master Key    │                               │
   │                     (32 bytes)    │                               │
   │                          │        │                               │
   │                     HKDF-Expand   │                               │
   │                    ┌─────┴─────┐  │                               │
   │                  encKey     macKey│                               │
   │                          │        │                               │
   │── passwordHash ──────────┼───────▶│── POST /identity/connect ───▶ │
   │   (PBKDF2, 1 iter.)      │        │          /token               │
   │                          │        │◀── access_token + encKey ───  │
   │                          │        │                               │
   │◀──────── encKey (encrypted) ──────│                               │
   │                          │        │                               │
   │── AES-CBC decrypt ───────┘        │                               │
   │   (HMAC-SHA256 verify)            │                               │
   │                                   │                               │
   │◀──── Vault items (encrypted) ─────│──── GET /api/sync ──────────▶ │
   │                                   │                               │
   │── AES-CBC decrypt (in browser) ──▶ Plaintext (never sent to server)
```

**Security guarantees:**
- Master password is **never transmitted** to any server
- Vault keys are held in **browser RAM only** (no LocalStorage)
- Tokens stored in **PHP session only** (server-side, never in the browser)
- All cryptographic operations via **Web Crypto API** (native browser code)

---

## 📋 Requirements

| Component | Version |
|---|---|
| Nextcloud | 31, 32 or 33 |
| PHP | 8.1+ |
| Node.js | 20+ |
| npm | 8.3+ |

---

## 🚀 Installation

### 1. Download the app

```bash
cd /var/www/html/apps   # or your Nextcloud apps directory
git clone https://github.com/derfips/nc_bitwarden.git
```

### 2. Build the JavaScript

```bash
cd nc_bitwarden
npm install
npm run build
```

### 3. Enable the app

```bash
sudo -u www-data php /var/www/html/occ app:enable nc_bitwarden
```

### 4. Configure

Nextcloud → **Settings → Personal → Bitwarden**

| Option | Description |
|---|---|
| Bitwarden Cloud (US) | `bitwarden.com` – default |
| Bitwarden Cloud (EU) | `bitwarden.eu` – GDPR compliant |
| Self-hosted | Your own Bit- or Vaultwarden instance |

---

## 🐳 Nextcloud AIO (Docker)

```bash
APP=/var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/apps

# Copy app
cp -r nc_bitwarden $APP/

# Build
cd $APP/nc_bitwarden
npm install && npm run build

# Enable
sudo docker exec --user www-data nextcloud-aio-nextcloud \
  php /var/www/html/occ app:enable nc_bitwarden
```

---

## 🏠 Self-hosted Bit- or Vaultwarden

### URL format

Enter only the base URL – **without** a trailing slash:

```
✅  https://vault.example.com
❌  https://vault.example.com/
❌  https://vault.example.com/api
```

### Bit- or Vaultwarden on the same network as Nextcloud

If Bit- or Vaultwarden uses an internal hostname or IP, Nextcloud must be allowed to connect locally:

```bash
sudo docker exec --user www-data nextcloud-aio-nextcloud \
  php /var/www/html/occ config:system:set \
  allow_local_remote_servers --value=true --type=bool
```

### Self-signed TLS certificate

Add your CA certificate to Nextcloud's trust store:

```bash
cp my-ca.crt /var/www/html/resources/config/ca-bundle.crt
```

---

## 🔧 Development

```bash
# Development mode with auto-rebuild
npm run dev

# Linting
npm run lint

# Production build
npm run build
```

### Project structure

```
nc_bitwarden/
├── appinfo/
│   ├── info.xml          # App metadata, NC version range
│   └── routes.php        # URL routing
├── lib/
│   ├── AppInfo/          # Bootstrap
│   ├── Controller/       # PHP endpoints (API proxy, settings)
│   ├── Service/          # Bitwarden proxy, user settings
│   └── Settings/         # NC personal settings page
├── src/
│   ├── services/
│   │   ├── api.js        # Axios wrapper for NC backend
│   │   └── crypto.js     # PBKDF2, Argon2id, AES-CBC, HKDF-Expand, RSA
│   ├── components/
│   │   ├── LoginForm.vue   # Master password entry
│   │   ├── VaultList.vue   # Sidebar with folders & entries
│   │   ├── ItemDetail.vue  # Entry detail view
│   │   ├── ItemForm.vue    # Create / edit dialog
│   │   ├── FieldRow.vue    # Reusable field row component
│   │   └── Settings.vue    # Server configuration
│   └── App.vue            # Root component + vault loader
└── templates/             # PHP templates for NC integration
```

---

## 🛡️ Security Notes

### AES-CBC browser warning

The browser may show the following console message:

> *AES-CBC and AES-CTR do not provide authentication by default...*

This is **not an error**. Bitwarden uses AES-CBC together with a separate HMAC-SHA256
(Encrypt-then-MAC), which is cryptographically sound. The warning is a generic browser
recommendation; this app deliberately follows the Bitwarden wire protocol for full
compatibility.

### Known limitations

- **Memory dump**: JavaScript strings are immutable – the master password cannot be
  securely wiped from the heap after use. This applies to all browser-based password
  managers and is an accepted trade-off.
- **Organisation vaults**: Require RSA-OAEP decryption of the user's private key
  (fully implemented). For very large vaults this may take a few seconds on first load.

---

## 🗺️ Roadmap

- [ ] Live TOTP code display (auto-refresh)
- [ ] Password generator
- [ ] Favourites view
- [ ] Offline cache (Service Worker)
- [ ] Bitwarden Send support

---

## 🤝 Contributing

Pull requests are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'Add: my feature'`)
4. Push the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

---

## 📄 License

[AGPL-3.0](LICENSE) – the same license as Nextcloud itself.

---

## 🙏 Credits

- [Bitwarden](https://bitwarden.com) – open-source password manager
- [Vaultwarden](https://github.com/dani-garcia/vaultwarden) – unofficial Bitwarden-compatible server
- [Nextcloud](https://nextcloud.com) – self-hosted cloud platform
- [@noble/hashes](https://github.com/paulmillr/noble-hashes) – pure-JS Argon2id implementation
