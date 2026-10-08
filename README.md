# ShulePay — School Finance System

**ShulePay** is a multi-school finance management system built for Tanzanian schools. It handles student registration, fee invoicing, payments, expenses, payroll, attendance, and SMS notifications — all in one platform.

Developed by **nexoryaTECH** · Deployed at [magrethschools.sc.tz](https://magrethschools.sc.tz)

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11 (PHP 8.2), Sanctum, Spatie Permissions |
| Frontend | Vue 3, Vite, CoreUI, Pinia, Vue Router |
| Database | MySQL / MariaDB |
| SMS | Kilakona Gateway |
| Infrastructure | Docker (nginx + php-fpm + supervisord), aaPanel, Let's Encrypt |
| CI/CD | GitHub Actions → Docker Hub → SSH deploy |

---

## Project Structure

```
shulepay/
├── backend/          # Laravel 11 API
│   ├── app/
│   ├── database/
│   ├── docker/       # nginx.conf, supervisord.conf
│   ├── Dockerfile
│   └── .env.example
├── frontend/         # Vue 3 SPA
│   ├── src/
│   ├── public/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── .env.example
├── docker-compose.prod.yml
├── deploy.sh
└── .github/workflows/deploy.yml
```

---

## Local Development Setup

### Prerequisites

- PHP 8.2, Composer
- Node.js 20, npm
- MySQL running locally

### Backend

```bash
cd backend
cp .env.example .env
# Edit .env — set DB_PASSWORD, SMS keys, etc.
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve        # runs on http://localhost:8000
```

### Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev              # runs on http://localhost:3000
```

### Default local users (after seeding)

| Name | Email | Role |
|------|-------|------|
| Amina Mmiliki | owner@gmail.com | owner |
| John Muhasibu | accountant@gmail.com | accountant |
| Super Admin | super@gmail.com | superadmin |

---

## User Roles

| Role | Access |
|------|--------|
| `superadmin` | Full system access + user/role management |
| `owner` | All finance, staff, budgets, academic years |
| `accountant` | Students, invoices, payments, expenses, reports |
| `head_teacher` / `headmaster` | Students (read), attendance, reports |
| `teacher_pri` / `teacher_sec` | Attendance only |
| `parent` | Own children's statements (read-only) |

---

## Languages (English / Kiswahili)

The language switcher in the header changes everything the user reads, including
printed documents. The frontend sends the chosen language on every request
(`X-Lang`), and the backend applies it (`SetLocale` middleware).

| What | Where | Rule |
|------|-------|------|
| Screens | `frontend/src/i18n/{en,sw}.js` | Use `t('section.key')`; add the key to **both** files |
| Dates | `frontend/src/utils/dates.js` | `toLocaleDateString(dateLocale(), …)`, never a fixed locale |
| Role / permission names | `utils/roles.js`, `utils/permissions.js` | Reuse the helpers; don't print raw role or permission names |
| Receipts, statements, reports, clearance | `backend/lang/{en,sw}/pdf.php` | Use `__('pdf.key')` in the Blade views |
| Excel / CSV headings | `backend/lang/{en,sw}/exports.php` | |
| Payment method, invoice status, school level | `backend/lang/{en,sw}/enums.php` | Enum `label()` is translated |
| API messages and validation errors | English text in code via `__('…')`, Swahili in `backend/lang/sw.json` and `lang/sw/validation.php` | |

Not translated on purpose: SMS sent to parents (their language, not the sender's),
school-entered data (class and term names), the bulk-import CSV header row (machine-read).

`php artisan test` fails if a printed document mixes languages, a message in the
code has no Swahili entry, or the two language files drift apart.

---

## Production Deployment

Deployment is fully automated via GitHub Actions on every push to `master`:

1. **backend-checks** — PHP lint (Pint) + tests
2. **frontend-checks** — ESLint + Vite build
3. **build-and-push** — Docker images pushed to Docker Hub
4. **deploy** — SSH into server, write `.env`, pull images, restart containers

### Manual deploy (if needed)

```bash
ssh user@<your-server-ip>
cd /www/shulepay
./deploy.sh deploy
```

### Required GitHub Secrets

| Secret | Description |
|--------|-------------|
| `DEPLOY_HOST` | Server IP |
| `DEPLOY_USER` | SSH user (`root`) |
| `DEPLOY_SSH_KEY` | Private SSH key |
| `APP_KEY` | Laravel app key |
| `DB_PASSWORD` | MySQL password for `shulepay` user |
| `DB_ROOT_PASSWORD` | MySQL root password |
| `DOCKERHUB_USERNAME` | Docker Hub username |
| `DOCKERHUB_TOKEN` | Docker Hub access token |
| `SMS_API_KEY` | Kilakona API key |
| `SMS_SECRET_KEY` | Kilakona secret key |
| `SMS_SENDER_ID` | SMS sender name |

---

## Architecture (Production)

```
Internet
   │  HTTPS (443)
   ▼
aaPanel nginx  ──────────────────────────────────────────
   │  reverse proxy → 127.0.0.1:8080
   ▼
Docker: shulepay_frontend (nginx, port 8080)
   │  /api/* and /storage/* → proxy_pass
   ▼
Docker: shulepay_backend (nginx + php-fpm + queue worker)
   │  host.docker.internal:3306
   ▼
aaPanel MariaDB 10.11  (database: shulepay)
```

### Docker volumes

| Volume | Purpose |
|--------|---------|
| `shulepay_backend_storage` | Laravel storage (logs, uploads, cache) |

---

## SSL Certificate

Managed by **aaPanel** (Let's Encrypt via HTTP-01 challenge).
aaPanel runs an automatic renewal cron every night at 01:31 — no manual action needed.

To re-issue or check status: **aaPanel → Website → magrethschools.sc.tz → SSL → Let's Encrypt**.

---

## Key Features

- **Multi-school** — separate data per school with full tenant isolation
- **Student management** — registration, guardians, clearance, bulk import
- **Fee management** — invoices, installments, payments, refunds, discounts
- **Expenses** — petty cash, payroll, suppliers, assets, budgets
- **Attendance** — class register with daily summaries
- **SMS notifications** — payment receipts, reminders via Kilakona
- **Reports** — financial summaries, collection trends, per-class breakdown
- **Audit log** — every financial action is logged with before/after values
- **Bilingual** — full English / Kiswahili interface (i18n)
- **Dark mode** — system / light / dark theme toggle

---

## License

Proprietary — nexoryaTECH © 2026. All rights reserved.
