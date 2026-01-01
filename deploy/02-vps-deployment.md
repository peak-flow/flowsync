# VPS Deployment Guide

Deploy FlowSync to a single VPS (DigitalOcean, Linode, Vultr, Hetzner, etc.)

## Prerequisites

- A VPS with Ubuntu 22.04 or 24.04 LTS
- A domain name pointing to your server's IP
- SSH access to the server

---

## Step 1: Initial Server Setup

### Connect to Server
```bash
ssh root@your-server-ip
```

### Create Deploy User
```bash
# Create user
adduser deploy
usermod -aG sudo deploy

# Setup SSH for deploy user
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Switch to deploy user
su - deploy
```

### Update System
```bash
sudo apt update && sudo apt upgrade -y
```

---

## Step 2: Install Required Software

### Add PHP Repository
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
```

### Install PHP 8.4
```bash
sudo apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-redis \
    php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl
```

### Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Install Node.js 20.x
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### Install PostgreSQL
```bash
sudo apt install -y postgresql postgresql-contrib
```

### Install Redis
```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

### Install nginx
```bash
sudo apt install -y nginx
sudo systemctl enable nginx
```

### Install Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
```

---

## Step 3: Configure PostgreSQL

### Create Database and User
```bash
sudo -u postgres psql
```

```sql
CREATE USER flowsync WITH PASSWORD 'your-secure-password';
CREATE DATABASE flowsync OWNER flowsync;
GRANT ALL PRIVILEGES ON DATABASE flowsync TO flowsync;
\q
```

---

## Step 4: Configure Redis

### Edit Redis Config
```bash
sudo nano /etc/redis/redis.conf
```

Set:
```
bind 127.0.0.1
maxmemory 256mb
maxmemory-policy allkeys-lru
```

Restart Redis:
```bash
sudo systemctl restart redis-server
```

---

## Step 5: Deploy Laravel Application

### Clone Repository
```bash
cd /var/www
sudo mkdir flowsync
sudo chown deploy:deploy flowsync
git clone https://github.com/your-repo/flowsync.git flowsync
cd flowsync
```

### Install PHP Dependencies
```bash
composer install --optimize-autoloader --no-dev
```

### Configure Environment
```bash
cp .env.example .env
nano .env
```

Set production values:
```env
APP_NAME=FlowSync
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://flowsync.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flowsync
DB_USERNAME=flowsync
DB_PASSWORD=your-secure-password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

SIGNALING_URL=https://flowsync.yourdomain.com
```

### Generate App Key
```bash
php artisan key:generate
```

### Run Migrations
```bash
php artisan migrate --force
```

### Build Frontend Assets
```bash
npm ci
npm run build
```

### Set Permissions
```bash
sudo chown -R deploy:www-data /var/www/flowsync
sudo chmod -R 775 /var/www/flowsync/storage
sudo chmod -R 775 /var/www/flowsync/bootstrap/cache
```

### Optimize Laravel
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Step 6: Deploy Signaling Server

### Install Dependencies
```bash
cd /var/www/flowsync/signaling
npm ci --omit=dev
```

### Configure Environment
```bash
cp .env.example .env
nano .env
```

Set:
```env
PORT=3001
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
LARAVEL_URL=https://flowsync.yourdomain.com
```

### Create Systemd Service
```bash
sudo nano /etc/systemd/system/flowsync-signaling.service
```

```ini
[Unit]
Description=FlowSync Signaling Server
After=network.target redis-server.service

[Service]
Type=simple
User=deploy
WorkingDirectory=/var/www/flowsync/signaling
ExecStart=/usr/bin/node server.js
Restart=on-failure
RestartSec=10
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=flowsync-signaling
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

### Enable and Start Service
```bash
sudo systemctl daemon-reload
sudo systemctl enable flowsync-signaling
sudo systemctl start flowsync-signaling
```

### Check Status
```bash
sudo systemctl status flowsync-signaling
```

---

## Step 7: Configure nginx

### Create Site Config
```bash
sudo nano /etc/nginx/sites-available/flowsync
```

```nginx
server {
    listen 80;
    server_name flowsync.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name flowsync.yourdomain.com;

    root /var/www/flowsync/public;
    index index.php;

    # SSL (will be configured by Certbot)
    # ssl_certificate /etc/letsencrypt/live/flowsync.yourdomain.com/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/flowsync.yourdomain.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Signaling Server (Socket.io)
    location /socket.io/ {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }

    # Deny hidden files
    location ~ /\. {
        deny all;
    }
}
```

### Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/flowsync /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## Step 8: Setup SSL with Let's Encrypt

```bash
sudo certbot --nginx -d flowsync.yourdomain.com
```

Follow prompts to:
1. Enter email for renewal notices
2. Agree to terms
3. Choose to redirect HTTP to HTTPS (recommended)

### Test Auto-Renewal
```bash
sudo certbot renew --dry-run
```

---

## Step 9: Update Laravel Signaling URL

Since nginx now proxies `/socket.io/` to the signaling server, update `.env`:

```bash
nano /var/www/flowsync/.env
```

Change:
```env
SIGNALING_URL=https://flowsync.yourdomain.com
```

Clear config cache:
```bash
php artisan config:cache
```

---

## Step 10: Setup Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## Step 11: Verify Deployment

### Check All Services
```bash
# PHP-FPM
sudo systemctl status php8.4-fpm

# nginx
sudo systemctl status nginx

# PostgreSQL
sudo systemctl status postgresql

# Redis
sudo systemctl status redis-server

# Signaling Server
sudo systemctl status flowsync-signaling
```

### Test Application
1. Visit `https://flowsync.yourdomain.com`
2. Create a room
3. Open in two browser windows
4. Test video chat

---

## Maintenance Commands

### View Logs
```bash
# Laravel logs
tail -f /var/www/flowsync/storage/logs/laravel.log

# Signaling server logs
sudo journalctl -u flowsync-signaling -f

# nginx logs
sudo tail -f /var/log/nginx/error.log
```

### Restart Services
```bash
sudo systemctl restart php8.4-fpm
sudo systemctl restart flowsync-signaling
sudo systemctl restart nginx
```

### Deploy Updates
```bash
cd /var/www/flowsync

# Pull changes
git pull origin main

# Update PHP dependencies
composer install --optimize-autoloader --no-dev

# Update Node dependencies (if signaling changed)
cd signaling && npm ci --omit=dev && cd ..

# Run migrations
php artisan migrate --force

# Rebuild assets
npm ci && npm run build

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart php8.4-fpm
sudo systemctl restart flowsync-signaling
```

---

## Troubleshooting

| Issue | Check |
|-------|-------|
| 502 Bad Gateway | `sudo systemctl status php8.4-fpm` |
| WebSocket fails | `sudo systemctl status flowsync-signaling` |
| Database errors | `sudo systemctl status postgresql` |
| SSL errors | `sudo certbot certificates` |
| Permission denied | Check `/var/www/flowsync` ownership |
