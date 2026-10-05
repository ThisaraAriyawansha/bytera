# M-Fixpro POS

Point-of-sale and repair-shop management for a computer and electronics repair shop: POS with shifts and split payments, repair job notes, bills, quotations, warranties, two-location stock (Stores → Showroom) with FIFO batches and serial numbers, suppliers, finance, salaries and an audit log.

Built with Laravel 13, MySQL 8, Blade, Tailwind CSS 3 and Alpine.js 3.

The staff user manual is built in and public at **`/manual`** (it is marked `noindex`).

## Requirements

| | Version |
|---|---|
| PHP | 8.3 or newer, with `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl`, `fileinfo` |
| Composer | 2.x |
| MySQL | 8.x (InnoDB, `utf8mb4`) |
| Node.js | 20.19+ or 22.12+ (needed to build assets only) |
| SMTP account | for receipts, job emails, payslips, statements, low-stock alerts and password reset codes |
| Cloudflare Turnstile | a site key + secret for the login / forgot / reset forms |

## Install (local)

1. **Get the code and PHP dependencies**

   ```bash
   composer install
   ```

2. **Install the front-end dependencies**

   ```bash
   npm install
   ```

3. **Create `.env`** and an app key

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Then edit `.env`:

   ```dotenv
   APP_NAME="M-Fixpro POS"
   APP_URL=http://localhost:8000

   DB_DATABASE=mfixpro          # create this empty database first (utf8mb4)
   DB_USERNAME=root
   DB_PASSWORD=

   # The first account. The seeder refuses to run without these.
   SUPER_ADMIN_EMAIL=you@example.com
   SUPER_ADMIN_PASSWORD=choose-a-strong-password

   # Cloudflare's always-pass test keys are fine for local work:
   TURNSTILE_SITE_KEY=1x00000000000000000000AA
   TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA

   # Use MAIL_MAILER=log locally to write emails to storage/logs instead of sending them.
   MAIL_MAILER=log
   MAIL_FROM_ADDRESS="pos@example.com"
   MAIL_FROM_NAME="M-Fixpro POS"
   ```

4. **Create the tables and the first data**

   ```bash
   php artisan migrate --seed
   ```

   This creates every table, the Super Admin from `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD`, the shop settings row (name "M-Fixpro") and all document counters at 0.

5. **Build the assets**

   ```bash
   npm run build
   ```

6. **Run it**

   ```bash
   php artisan serve
   ```

   Open http://localhost:8000 and sign in as the Super Admin. Then set the shop name, phone, email and address in **Settings → Shop Info**: they appear on every printout and email.

For front-end work, `composer run dev` runs the server and Vite with hot reload together.

### Tests

```bash
php artisan test
```

Tests use an in-memory SQLite database (see `phpunit.xml`), so they never touch your MySQL data.

## Deployment notes

### Server setup

- Point the web server's document root at **`public/`**, never the project root.
- Serve over **HTTPS**. Logins, sessions and the Turnstile check all assume it.
- PHP must be able to write to `storage/` and `bootstrap/cache/`.
- No queue worker or scheduler is needed: emails are sent during the request, and nothing runs on a timer.

### Production `.env`

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pos.your-shop.lk

SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com     # or your provider
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...            # for Gmail, an App Password, not the account password
MAIL_FROM_ADDRESS="pos@your-shop.lk"
MAIL_FROM_NAME="M-Fixpro POS"

TURNSTILE_SITE_KEY=...       # real keys from the Cloudflare dashboard,
TURNSTILE_SECRET_KEY=...     # with your production hostname added to the widget
```

Sessions and cache are stored in the database (`SESSION_DRIVER=database`, `CACHE_STORE=database`). Their tables are created by the migrations.

### First deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # or build on CI and upload public/build
php artisan key:generate         # first deploy only; never change APP_KEY afterwards
php artisan migrate --seed --force
php artisan optimize
```

### Later deploys

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

Run `php artisan optimize` after every deploy and after any `.env` change. It caches config, routes and views, so `.env` edits do nothing until you run it again.

> **Don't re-run `db:seed` on a live system.** The seeder sets the Super Admin's password back to `SUPER_ADMIN_PASSWORD`. Once the first deploy is done, remove `SUPER_ADMIN_PASSWORD` from the production `.env`.

### Things to know

- **Behind Cloudflare or a load balancer:** configure trusted proxies (`$middleware->trustProxies(at: '*')` in `bootstrap/app.php`, or the proxy's IP ranges). Otherwise every visitor shares the proxy's IP, which breaks the login rate limit (10 attempts per minute per email + IP) and Turnstile's IP check.
- **Staff browsers need internet access:** the "Download PDF" and bill-email buttons load html2canvas and jsPDF from cdnjs.cloudflare.com, and fonts come from Google Fonts.
- **Low-stock emails** go to the addresses in Settings → Low Stock Alerts. If the list is empty, no alert is sent.
- **Backups:** everything lives in MySQL. Schedule a daily `mysqldump --single-transaction mfixpro` and keep copies off the server. Settings → Data tools (Super Admin only) can also export any table as CSV or JSON.
- **Laravel Cloud** works as a host as-is: set the same environment variables, attach a MySQL database and use `php artisan migrate --force` as the deploy command.
