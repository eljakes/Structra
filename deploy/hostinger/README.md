# Navkwa Build Hostinger Go-Live Runbook

This runbook deploys Navkwa Build ERP and Navkwa Build Cloud Console on a
single domain:

- ERP: `https://app.navkwabuild.com`
- Cloud Console: `https://app.navkwabuild.com/cloud-console`
- API: `https://app.navkwabuild.com/api/v1`

The frontend should keep `VITE_API_URL=` blank in production so browser calls
use the same origin through `/api/v1`.

## 1. Hostinger VPS

Use **Hostinger VPS**, not Hostinger shared/web hosting. Navkwa Build needs
PostgreSQL, Nginx/PHP-FPM control, Supervisor queue workers, cron, and server
environment variables.

Create an Ubuntu 24.04 Hostinger VPS with weekly backups/snapshots enabled. For
launch, start with at least 2 vCPU and 4 GB RAM. Configure the VPS firewall to
allow:

- SSH: port `22` from your trusted IPs
- HTTP: port `80` from anywhere
- HTTPS: port `443` from anywhere

Point DNS after the VPS is created:

```text
A     app     <HOSTINGER_VPS_IPV4>
AAAA  app     <HOSTINGER_VPS_IPV6>   # optional, only if IPv6 is enabled
```

## 2. Server Packages

SSH into the server as a sudo-capable user and install the runtime:

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y \
  nginx supervisor cron git unzip curl ca-certificates \
  postgresql postgresql-contrib postgresql-client redis-tools \
  php8.3-cli php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml \
  php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl
```

Install Composer:

```bash
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
test "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

Install Node 20+ using your preferred trusted Node distribution, then verify:

```bash
node -v
npm -v
php -v
composer --version
```

## 3. PostgreSQL Database

Generate a strong production database password on the VPS:

```bash
openssl rand -base64 32
```

Create a clean production database and application user. Replace
`PASTE_STRONG_PASSWORD_HERE` with the generated password:

```bash
sudo -u postgres psql
```

Inside `psql`:

```sql
CREATE USER navkwabuild_app WITH PASSWORD 'PASTE_STRONG_PASSWORD_HERE';
CREATE DATABASE navkwabuild OWNER navkwabuild_app;
GRANT CONNECT ON DATABASE navkwabuild TO navkwabuild_app;
\c navkwabuild
GRANT USAGE, CREATE ON SCHEMA public TO navkwabuild_app;
\q
```

Confirm the app user can connect:

```bash
PGPASSWORD='PASTE_STRONG_PASSWORD_HERE' psql \
  -h 127.0.0.1 \
  -p 5432 \
  -U navkwabuild_app \
  -d navkwabuild \
  -c 'select current_database(), current_user;'
```

## 4. Application Files

Clone the repository:

```bash
sudo mkdir -p /var/www/navkwabuild
sudo chown -R "$USER":www-data /var/www/navkwabuild
git clone https://github.com/eljakes/Structra.git /var/www/navkwabuild/current
cd /var/www/navkwabuild/current
```

Create the backend production env:

```bash
cp backend/.env.production.example backend/.env
nano backend/.env
```

Production must use real values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.navkwabuild.com
FRONTEND_URL=https://app.navkwabuild.com
CORS_ALLOWED_ORIGINS=https://app.navkwabuild.com
APP_VERSION=2026.07.31-1
NAVKWA_BUILD_SEED_DEVELOPMENT=false
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=navkwabuild
DB_USERNAME=navkwabuild_app
DB_PASSWORD=<the-password-you-generated>
DB_SSLMODE=require
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-password>
MAIL_FROM_ADDRESS=no-reply@navkwabuild.com
QUEUE_CONNECTION=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
```

Generate the production app key and paste it into `APP_KEY`:

```bash
cd /var/www/navkwabuild/current/backend
php artisan key:generate --show
```

Do not copy local `.env` values to production.

If you later move PostgreSQL to a managed external database, keep the same
Laravel keys but replace `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and any TLS
certificate settings required by that provider.

## 5. Build And Deploy

Run the deployment script:

```bash
cd /var/www/navkwabuild/current
./deploy/hostinger/scripts/deploy-production.sh
```

This installs dependencies, builds the frontend, checks production safety,
runs migrations with `--force`, caches Laravel config/routes/views, and
restarts workers when Supervisor is already configured.

## 6. Nginx

The generic Laravel Nginx config is not enough for this project because Navkwa
Build serves a React frontend and a Laravel API from one domain. Install this
repo's Nginx site:

```bash
sudo cp deploy/hostinger/nginx/navkwabuild.conf /etc/nginx/sites-available/navkwabuild
sudo ln -s /etc/nginx/sites-available/navkwabuild /etc/nginx/sites-enabled/navkwabuild
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

If your PHP-FPM socket is not `/run/php/php8.3-fpm.sock`, edit the Nginx file:

```bash
ls /run/php/php*-fpm.sock
```

## 7. HTTPS

After DNS resolves to the server, issue TLS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d app.navkwabuild.com
sudo certbot renew --dry-run
```

## 8. Queue Worker And Scheduler

Install Supervisor worker config:

```bash
sudo cp deploy/hostinger/supervisor/navkwabuild-worker.conf /etc/supervisor/conf.d/navkwabuild-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Install Laravel scheduler:

```bash
sudo cp deploy/hostinger/cron/navkwabuild-scheduler /etc/cron.d/navkwabuild-scheduler
sudo chmod 0644 /etc/cron.d/navkwabuild-scheduler
```

## 9. First Cloud Console Admin

Create the first production Cloud Console administrator:

```bash
cd /var/www/navkwabuild/current/backend
php artisan navkwabuild:platform-admin admin@navkwabuild.com --create
```

Sign in at `https://app.navkwabuild.com/cloud-console`, change the temporary
password immediately, and enable MFA.

## 10. Health Checks

Run:

```bash
cd /var/www/navkwabuild/current
./deploy/hostinger/scripts/healthcheck.sh
```

Expected:

- Frontend returns `200`
- `/api/v1/auth/me` returns `401`, proving the API is reachable and protected
- `php artisan navkwabuild:production-check --strict` passes
- Supervisor shows the worker running

## 11. Backups

Hostinger VPS backups/snapshots protect the VM disk, but database and uploaded
file backups should also be exported off-server.

Create a backup env file:

```bash
sudo mkdir -p /etc/navkwabuild
sudo cp deploy/hostinger/backup.env.example /etc/navkwabuild/backup.env
sudo chmod 0600 /etc/navkwabuild/backup.env
sudo nano /etc/navkwabuild/backup.env
```

Run the backup script manually first:

```bash
sudo ./deploy/hostinger/scripts/backup-postgres-and-storage.sh
```

Then schedule it with cron after confirming the backup files are valid.

## Cutover Rule

Do not open the app to customers until this passes on the production server:

```bash
cd /var/www/navkwabuild/current/backend
php artisan navkwabuild:production-check --strict
```
