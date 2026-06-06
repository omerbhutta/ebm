# Email Bounce Monitor (EBM)

**A self-hosted PHP application that monitors Microsoft 365 mailboxes for non-delivery reports (NDRs) and keeps a suppression list your sending systems can query in real time.**

Powered by [E-Services 360](https://eservices360.com).

---

## ✨ Features

- 📡 **Microsoft Graph integration** — connects to M365 using OAuth `client_credentials` (no user interaction required for scans)
- 📬 **Multi-mailbox scanning** — monitor an unlimited number of M365 mailboxes from a single dashboard
- 📋 **Bounce log** — searchable, filterable history of every NDR discovered
- ⊘ **Suppression list** — automatically populated from NDRs; address-level + domain-level rollups
- 🔌 **HTTP API** — `POST /api/check` (or `/check.php`) returns whether one or many addresses are blocked, with optional bounce count
- 🌓 **Dark + light themes** — user-toggleable, with a configurable default
- 🔐 **Two-tier access** — separate viewer and admin passwords (hashed with `password_hash()`)
- 🛡️ **Hardened** — CSRF tokens, rate-limited login, secure session handling, `.htaccess`-protected storage and config
- 📤 **CSV + Excel exports** — one click to export the entire bounce log or suppression list
- 🧰 **Admin panel** — manage mailboxes, rotate API keys, view activity log, change passwords, configure theme/retention/cache
- 🪄 **5-step install wizard** — no manual SQL, no editing config files by hand

## 📐 Requirements

- **PHP 8.0+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`, `intl`, `fileinfo`
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Apache 2.4** with `mod_rewrite` enabled (or Nginx — see [`INSTALL-NGINX.md`](INSTALL-NGINX.md) coming soon)
- **Microsoft 365 tenant** + an App Registration with **Microsoft Graph → Application permission → Mail.Read** (admin-consented)

Tested primarily on **Laragon** and **WAMP** on Windows, but it runs on any standard LAMP/LEMP stack.

## 🚀 Quick Start

1. **Clone or extract** the project into your web root:
   ```
   C:\laragon\www\ebm\
   ```
2. **Make these directories writable** by the web server:
   ```
   config/        (EBM writes installed.php here on first run)
   storage/cache/ (runtime cache)
   storage/logs/  (activity log)
   storage/locks/ (install lock + rate-limit lockfiles)
   ```
3. **Browse to the project** in your browser:
   ```
   http://localhost/ebm/
   ```
4. **Walk through the 5-step wizard:**
   1. *Welcome* — feature overview
   2. *Requirements* — automatic check of PHP version, extensions, folder permissions
   3. *Database* — host / database / user / password (database is created if missing)
   4. *Graph API* — Tenant ID, Client ID, Client Secret (live-tested)
   5. *Security* — app name, viewer password, admin password
5. **Sign in** at `/login` (viewer) or `/admin/login` (admin)

## 🔌 Microsoft Graph Setup (Step-by-Step)

1. Open **[Microsoft Entra admin center](https://entra.microsoft.com)** → *Identity* → *Applications* → *App registrations* → **New registration**.
2. **Name**: e.g. `EBM Bounce Monitor`. **Supported account types**: *Single tenant*. Click **Register**.
3. On the **Overview** page, copy the following values — you'll paste them into the EBM wizard:
   - **Directory (tenant) ID** → EBM: *Tenant ID*
   - **Application (client) ID** → EBM: *Client ID*
4. Go to **Certificates & secrets** → **New client secret** → set expiry (12 months recommended) → **Add**.
   - Copy the **Value** column **immediately** (the secret is only shown once) → EBM: *Client Secret*
5. Go to **API permissions** → **Add a permission** → **Microsoft Graph** → **Application permissions** → check **Mail.Read** → **Add permissions**.
6. Click **Grant admin consent for `<your tenant>`** and confirm.
7. The EBM wizard's *Test Graph Connection* button will verify everything is wired up correctly.

### Required mailbox permission

The Graph app has **tenant-wide** `Mail.Read`, so it can read any mailbox in the directory by default. If your organisation restricts access to specific mailboxes, your Exchange admin must grant access explicitly, e.g.:

```powershell
Add-MailboxPermission -Identity "bounces@contoso.com" -User "<EBM-APP-ID>" -AccessRights FullAccess -AutoMapping $false
```

## 🧪 Testing the API

After installation, your API key is shown on **Admin → Security**. The endpoint is:

```
POST /api/check
Headers: X-Api-Key: <your-key>
Body (JSON or form-encoded):
    { "emails": ["alice@example.com", "bob@example.com"] }
```

You can also GET a single address:

```
GET /api/check?email=alice@example.com&key=<your-key>
```

Response:

```json
{
  "checked": 2,
  "blocked": 1,
  "allowed": 1,
  "results": [
    { "email": "alice@example.com", "blocked": true,  "bounce_count": 3, "reason": "User unknown" },
    { "email": "bob@example.com",   "blocked": false, "bounce_count": 0 }
  ]
}
```

The legacy `check.php` URL still works for backward compatibility with older sending integrations.

## ⚙️ Configuration

All runtime settings live in the `settings` database table and can be edited from **Admin → System** / **Admin → Security**:

| Setting | Default | Description |
|---|---|---|
| `app_name` | `Email Bounce Monitor` | Header + browser title |
| `app_tagline` | (empty) | Subtitle under the brand |
| `footer_text` | `Powered by E-Services 360` | Page footer |
| `footer_url` | `https://eservices360.com` | Footer link target |
| `theme` | `dark` | `dark` or `light` |
| `theme_toggle_enabled` | `1` | Show theme toggle button |
| `monitor_folders` | `{"Inbox":"inbox","Junk Email":"junkemail","Deleted Items":"deleteditems"}` | Graph folder map (JSON) |
| `cache_ttl` | `300` | Seconds — affects dashboard summary |
| `session_ttl` | `28800` | Seconds (8h) |
| `retention_days` | `0` | 0 = keep forever; otherwise prune `processed_ndrs` older than N days |
| `login_rate_max` | `5` | Failed-login attempts allowed per window |
| `login_rate_window` | `900` | Window length in seconds |

## 🗂 Project Structure

```
ebm/
├── .htaccess                 ← URL rewriting, hides sensitive dirs
├── index.php                 ← front controller (boot → dispatch)
├── check.php                 ← legacy API shim
├── app/
│   ├── bootstrap.php         ← autoloader, error handler, session start
│   ├── routes.php            ← all route definitions
│   ├── Core/                 ← framework (App, Router, Auth, Csrf, Settings, …)
│   ├── Services/             ← Graph, Bounce, Mailbox, Suppression, Install, Export
│   ├── Controllers/          ← Install, Auth, Dashboard, Bounce, Suppression, Api, Admin/*
│   └── Views/                ← layouts, partials, install/, auth/, dashboard/, …
├── assets/                   ← css, js, img (served directly by Apache)
├── config/                   ← installed.php (created on first run)
├── database/                 ← schema.sql (executed by installer)
└── storage/                  ← cache, logs, locks (with .htaccess deny rules)
```

## 🔁 Updating from an older non-installer version

This is a clean rewrite. To migrate from a pre-2.0 install:

1. Back up your database.
2. Move the new files alongside your existing app.
3. Browse to `/install` and walk through the wizard — choose a **new** database name (or drop the old schema first).
4. Re-enter your Microsoft Graph credentials and set new passwords.
5. After install, copy the suppression list rows from the old database to the new one (schema is identical: `SELECT * FROM suppression_list` → `INSERT INTO suppression_list …`).
6. Delete the old `config.php`, `db.php`, `admin.php`, `body.php`, `suppression.php`.

## 🛡️ Security Recommendations

- Run EBM **behind HTTPS** (Let's Encrypt or your reverse proxy).
- Set a strong `app_name`, and rotate the API key regularly.
- Restrict `/install` after setup by **not** deleting `config/installed.php` (the installer detects it and refuses to re-run).
- Restrict by IP at the web-server level if EBM is only used on an internal network.
- Enable Windows Defender or your anti-virus on the server.

## 🐛 Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `404` on every route | Apache `mod_rewrite` not enabled, or `.htaccess` not being read. Try `AllowOverride All` in your vhost. |
| "Requirements: config/ is not writable" | Grant write permission: in Laragon, the Apache user is `SYSTEM` — `icacls config /grant Everyone:(OI)(CI)F`. |
| "Graph API failed: AADSTS700016" | The app is multi-tenant but you signed in with a non-matching tenant. Use a *Single-tenant* registration. |
| "Mailbox not accessible" | Grant `Mail.Read` at tenant level, or run `Add-MailboxPermission` for each mailbox. |
| Bounces not detected | The NDR was outside the configured folders, or the search query did not match. Try `subject:undeliverable` (default). |
| Login page shows `rate limited` | Wait `login_rate_window` seconds, or use the admin panel to bump the limit. |

## 📜 License

© E-Services 360 — All rights reserved. Distributed under the MIT License for the framework code; the EBM application code is provided as-is without warranty.

## 🔒 Security & Privacy

This repository is safe to publish publicly. **No secrets, credentials, or user data are committed.** Sensitive material lives only on your server.

### What is in the repo

- Application source (PHP, JS, CSS, views)
- Database schema (`database/schema.sql` — DDL only, no data)
- Static assets and installer assets
- `.gitignore` covering all runtime / secret paths

### What is **not** in the repo (gitignored)

| Path | Why |
| --- | --- |
| `config/installed.php` | Generated by the installer; contains your **MySQL password** in plaintext |
| `storage/cache/*.cache` | Cached Graph API **access tokens (JWTs)**, NDR message bodies, mailbox scans |
| `storage/logs/app-*.log` | Activity log — contains **user IPs, login events, tenant IDs** |
| `storage/locks/*` | Install lock and rate-limit files |
| `cache/*.json` | Legacy NDR cache (kept on disk for compatibility) |
| `nbproject/`, `.idea/`, `.vscode/` | IDE settings |
| `.env*` | Any future environment files |

### Where secrets actually live

- **Database password** → `config/installed.php` (regenerated on every install; rotate by re-running the installer or editing the file)
- **Microsoft Graph client secret** → `settings` table, key `graph_client_secret` (rotated via **Admin → Graph API**)
- **Admin / viewer passwords** → `settings` table, keys `admin_password_hash` and `viewer_password_hash` (PHP `password_hash()`)
- **API key for `check.php`** → `settings` table, key `check_api_key` (rotated via **Admin → Security**)
- **JWTs** → `storage/cache/graph_token_*.cache`, written with restrictive `0600` perms where possible; TTL ~1 hour

### Pre-publish checklist

Before pushing to a public repo, run:

```bash
# 1. Confirm no secrets are staged
git status --ignored --short

# 2. Confirm no hardcoded creds in code
grep -rE 'password|secret|api[_-]?key|token' app/ database/ assets/ index.php check.php
# (matches should only be variable names, form field names, doc comments — never real values)

# 3. Confirm the .gitignore is honoured
git check-ignore -v config/installed.php
git check-ignore -v storage/cache/graph_token_*.cache
# Both should print a line and exit 0
```

### If you accidentally committed a secret

1. **Rotate it immediately** (DB password, Graph client secret, API key, admin/viewer passwords).
2. Remove from git history:
   ```bash
   git rm --cached <file>
   git commit -m "Remove accidentally committed secret"
   # For history rewriting (rewrites all commits — destructive):
   # git filter-branch --force --index-filter \
   #   "git rm --cached --ignore-unmatch <file>" HEAD
   # OR use BFG Repo-Cleaner (faster): https://rtyley.github.io/bfg-repo-cleaner/
   ```
3. Force-push if the repo was already public (or delete the repo and re-create).
4. Re-issue any leaked credentials.

---

## 🤝 Credits

- **Author**: E-Services 360 — [eservices360.com](https://eservices360.com)
- **Powered by**: PHP, MySQL/MariaDB, Microsoft Graph
- **Inspired by**: every "your message could not be delivered" report a sender has ever received
