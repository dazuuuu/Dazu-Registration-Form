# Pentagon UI + Dazu Backend — cPanel deployment guide

This project combines:

- **Frontend:** [Pentagon-UI-online](https://github.com/dazuuuu/Pentagon-UI-online.git) (static HTML / Elementor export)
- **Backend:** Dazu Registration-System PHP app (`app/`, `public/api/`, admin, student portal)

---

## 1. What gets integrated

| Pentagon UI feature | Backend endpoint | Data source |
|---------------------|------------------|-------------|
| Packages / offers | `GET /api/packages.php` | `courses` table |
| Featured offers | `GET /api/offers.php` | Active courses |
| Contact forms | `POST /api/contact.php` | `enquiries` table + admin email |
| Contact details | `GET /api/site/contact.php` | `site_settings` |
| Course apply | `public/courses/view.php` or `POST /api/registrations/submit.php` | Registrations |
| Testimonials | `GET /api/testimonials/list.php` | `testimonials` table |
| Blogs / projects | `GET /api/blog/*`, `GET /api/projects.php` | DB |

The file `public/js/pentagon-bridge.js` is injected into every Pentagon HTML page and wires forms + dynamic grids automatically.

---

## 2. cPanel folder layout

Upload the full project so the structure looks like this:

```
/home/youruser/
├── dazuaihub.com/          ← cPanel document root (or use public_html)
│   ├── index.html          ← Pentagon homepage (after sync)
│   ├── contact-us/
│   ├── packages/
│   ├── wp-content/
│   ├── js/pentagon-bridge.js
│   ├── api/                ← PHP JSON endpoints
│   ├── admin/              ← Admin panel (PHP)
│   ├── auth/
│   └── …
├── app/                    ← OUTSIDE public_html (recommended)
│   └── v1/
├── databases/
├── storage/
├── vendor/
├── .env
└── composer.json
```

**Recommended:** Point the domain document root to the `public/` folder contents, and place `app/`, `.env`, `vendor/` **one level above** the web root so they are not publicly accessible.

If your host only allows `public_html`, upload everything into `public_html` but protect `app/` with `.htaccess` (included below).

---

## 3. Step-by-step: deploy on cPanel

### Step A — Create MySQL database

1. cPanel → **MySQL Databases**
2. Create database: e.g. `youruser_registration`
3. Create user + strong password, add user to database (ALL PRIVILEGES)
4. cPanel → **phpMyAdmin** → Import `databases/migrations/*.sql` (or your existing dump)

### Step B — Upload files

**Option 1 — Git (if Terminal/SSH available):**

```bash
cd ~
git clone https://github.com/dazuuuu/Dazu-Registration-Form.git pentagon-app
cd pentagon-app
composer install --no-dev
cp .env.example .env
# edit .env with your DB credentials
./scripts/sync-pentagon-ui.sh
```

**Option 2 — File Manager / FTP:**

1. Upload the repo ZIP and extract
2. Run `composer install` locally or upload the `vendor/` folder
3. On your PC, run the sync script (see section 4) and upload the merged `public/` folder

### Step C — Configure `.env`

Create `.env` in the project root (same level as `app/`):

```env
DB_HOST=localhost
DB_NAME=youruser_registration
DB_USER=youruser_dbuser
DB_PASS=your_strong_password

# Leave empty when domain docroot IS the site folder (standard cPanel)
APP_WEB_ROOT=

# If document root is exactly your site folder:
# APP_PUBLIC_ROOT=/home/youruser/dazuaihub.com
```

### Step D — Point domain to site folder

1. cPanel → **Domains** → your domain → Document root
2. Set to the folder containing `index.html` and `api/` (e.g. `dazuaihub.com` or `public_html`)

### Step E — PHP version

cPanel → **Select PHP Version** → choose **PHP 8.1+** and enable extensions:

- `pdo_mysql`
- `mbstring`
- `curl`
- `json`
- `openssl`

### Step F — Run migrations (first time)

Visit in browser (replace with your domain):

```
https://yourdomain.com/admin/sms/migrate.php
https://yourdomain.com/admin/courses/migrate_duration.php
```

### Step G — Test endpoints

```
https://yourdomain.com/api/packages.php
https://yourdomain.com/api/offers.php
https://yourdomain.com/api/site/contact.php
```

Contact form: open `https://yourdomain.com/contact-us/` and submit — should save to **Admin → Enquiries**.

---

## 4. Sync Pentagon UI (local or server)

From the project root:

```bash
chmod +x scripts/sync-pentagon-ui.sh
./scripts/sync-pentagon-ui.sh
```

This clones [Pentagon-UI-online](https://github.com/dazuuuu/Pentagon-UI-online.git), copies static files into `public/`, **keeps** `api/`, `admin/`, `auth/`, etc., and injects `pentagon-bridge.js` into all HTML pages.

To use a local clone you already have:

```bash
./scripts/sync-pentagon-ui.sh "C:/path/to/Pentagon-UI-online"
```

---

## 5. Run locally (Ampps / XAMPP)

```
C:\Program Files\Ampps\www\Pentagon Quest\
├── app\
├── public\          ← Ampps vhost or http://localhost/Pentagon%20Quest/public/
├── .env
└── vendor\
```

1. Clone repo into `Pentagon Quest`
2. `composer install`
3. Copy `.env.example` → `.env` (use Ampps MySQL credentials)
4. Run sync script (Git Bash or WSL):

```bash
./scripts/sync-pentagon-ui.sh
```

5. Open: `http://localhost/Pentagon%20Quest/public/`

For Ampps, `APP_WEB_ROOT` is usually auto-detected as `/Pentagon Quest/public`.

---

## 6. API reference (Pentagon bridge)

### Packages (courses)

```
GET /api/packages.php?limit=12
```

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "title": "Python Programming",
      "description": "…",
      "price": 25000,
      "price_label": "KES 25,000",
      "duration": "3 Months",
      "image": "https://…",
      "url": "/courses/view.php?id=1"
    }
  ]
}
```

### Contact form

```
POST /api/contact.php
Content-Type: application/json

{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "0712345678",
  "service": "Cybersecurity course",
  "message": "I would like more info"
}
```

### Offers

```
GET /api/offers.php?limit=6
```

---

## 7. Protect app/ above public_html

If `app/` must sit inside the web root, add `app/.htaccess`:

```apache
Require all denied
```

---

## 8. Troubleshooting

| Problem | Fix |
|---------|-----|
| `couldn't find remote ref cursor/bonfire-ui-integration-1217` | Use branch `cursor/pentagon-ui-backend-integration-1217` or `main` after merge |
| Contact form does nothing | Check browser console; verify `/api/contact.php` returns JSON; ensure `pentagon-bridge.js` is loaded |
| Packages empty | Add active courses in Admin → Courses; test `/api/packages.php` directly |
| 500 on API | Check `.env` DB credentials; cPanel error log |
| CSS/images broken | Re-run sync script; ensure `wp-content/` uploaded fully (~300MB) |
| Admin login | `https://yourdomain.com/admin/` or `https://yourdomain.com/auth/login.php` |

---

## 9. Branches

| Branch | Purpose |
|--------|---------|
| `main` | Stable PHP backend + Dazu UI |
| `cursor/pentagon-ui-backend-integration-1217` | Pentagon UI sync + API bridge |
| `cursor/bonfire-ui-integration-1217` | Earlier Bonfire theme port (optional) |

After testing, merge the pentagon integration PR into `main` and deploy `main` on cPanel.
