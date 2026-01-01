# PaaS Deployment Guide

Deploy FlowSync to Platform-as-a-Service providers with zero server management.

---

## Option A: Railway (Recommended)

Railway provides the easiest deployment with automatic SSL and managed databases.

### Prerequisites
- GitHub account
- Railway account (https://railway.app)

### Step 1: Prepare Repository

Create a `Procfile` in project root:
```
web: php artisan serve --host=0.0.0.0 --port=$PORT
```

Create `nixpacks.toml` in project root:
```toml
[phases.setup]
nixPkgs = ["php84", "php84Extensions.pgsql", "php84Extensions.redis", "nodejs_20"]

[phases.install]
cmds = [
    "composer install --optimize-autoloader --no-dev",
    "npm ci",
    "npm run build"
]

[phases.build]
cmds = [
    "php artisan config:cache",
    "php artisan route:cache",
    "php artisan view:cache"
]

[start]
cmd = "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT"
```

### Step 2: Create Railway Project

1. Go to https://railway.app/new
2. Click **"Deploy from GitHub repo"**
3. Select your FlowSync repository
4. Railway will auto-detect Laravel

### Step 3: Add PostgreSQL

1. In your Railway project, click **"+ New"**
2. Select **"Database" → "PostgreSQL"**
3. Railway auto-creates and links the database

### Step 4: Add Redis

1. Click **"+ New"**
2. Select **"Database" → "Redis"**
3. Railway auto-creates and links Redis

### Step 5: Deploy Signaling Server

1. Click **"+ New" → "GitHub Repo"**
2. Select the same repo
3. Click on the new service → **Settings**
4. Set **Root Directory** to `signaling`
5. Set **Start Command** to `node server.js`

### Step 6: Configure Environment Variables

#### Laravel Service Variables:
```
APP_NAME=FlowSync
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:generate-with-php-artisan-key-generate
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}

DB_CONNECTION=pgsql
DATABASE_URL=${{Postgres.DATABASE_URL}}

REDIS_URL=${{Redis.REDIS_URL}}
CACHE_STORE=redis
SESSION_DRIVER=redis

SIGNALING_URL=https://${{signaling.RAILWAY_PUBLIC_DOMAIN}}
```

#### Signaling Service Variables:
```
PORT=3001
REDIS_URL=${{Redis.REDIS_URL}}
LARAVEL_URL=https://${{Laravel.RAILWAY_PUBLIC_DOMAIN}}
```

> **Note:** Update `signaling/server.js` to parse `REDIS_URL` environment variable.

### Step 7: Generate Custom Domains (Optional)

1. Go to service **Settings → Networking**
2. Click **"Generate Domain"** for public URL
3. Or add custom domain and configure DNS

### Step 8: Verify Deployment

1. Visit your Laravel service URL
2. Create a room and test

---

## Option B: Render

### Step 1: Prepare Repository

Create `render.yaml` in project root:
```yaml
services:
  # Laravel Web Service
  - type: web
    name: flowsync-web
    env: php
    buildCommand: |
      composer install --optimize-autoloader --no-dev
      npm ci && npm run build
      php artisan config:cache
      php artisan route:cache
      php artisan view:cache
    startCommand: php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: APP_KEY
        generateValue: true
      - key: DATABASE_URL
        fromDatabase:
          name: flowsync-db
          property: connectionString
      - key: REDIS_URL
        fromService:
          name: flowsync-redis
          type: redis
          property: connectionString

  # Signaling Server
  - type: web
    name: flowsync-signaling
    env: node
    rootDir: signaling
    buildCommand: npm ci
    startCommand: node server.js
    envVars:
      - key: PORT
        value: 3001
      - key: REDIS_URL
        fromService:
          name: flowsync-redis
          type: redis
          property: connectionString

databases:
  - name: flowsync-db
    plan: free
    databaseName: flowsync

  - name: flowsync-redis
    type: redis
    plan: free
```

### Step 2: Deploy on Render

1. Go to https://render.com
2. Click **"New" → "Blueprint"**
3. Connect your GitHub repository
4. Render will read `render.yaml` and create all services

### Step 3: Configure Environment

After deployment, add remaining variables in Render dashboard:
- `APP_URL` = Your web service URL
- `SIGNALING_URL` = Your signaling service URL
- `LARAVEL_URL` = Your web service URL (for signaling)

---

## Option C: Fly.io

### Step 1: Install Fly CLI
```bash
curl -L https://fly.io/install.sh | sh
fly auth login
```

### Step 2: Create fly.toml for Laravel
```toml
app = "flowsync-web"
primary_region = "ord"

[build]
  [build.args]
    PHP_VERSION = "8.4"
    NODE_VERSION = "20"

[env]
  APP_ENV = "production"
  LOG_CHANNEL = "stderr"

[http_service]
  internal_port = 8080
  force_https = true
  auto_stop_machines = true
  auto_start_machines = true
  min_machines_running = 1

[[services]]
  protocol = "tcp"
  internal_port = 8080

  [[services.ports]]
    port = 80
    handlers = ["http"]

  [[services.ports]]
    port = 443
    handlers = ["tls", "http"]
```

### Step 3: Create Dockerfile
```dockerfile
FROM serversideup/php:8.4-fpm-nginx

# Install Node.js for asset building
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

WORKDIR /var/www/html

# Copy application
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev
RUN npm ci && npm run build

# Laravel optimizations
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

### Step 4: Deploy
```bash
# Create PostgreSQL
fly postgres create --name flowsync-db

# Create Redis
fly redis create --name flowsync-redis

# Deploy Laravel
fly launch
fly secrets set APP_KEY=base64:your-key-here
fly secrets set DATABASE_URL=postgres://...
fly secrets set REDIS_URL=redis://...
fly deploy
```

### Step 5: Deploy Signaling Server

Create `signaling/fly.toml`:
```toml
app = "flowsync-signaling"
primary_region = "ord"

[build]

[env]
  NODE_ENV = "production"

[http_service]
  internal_port = 3001
  force_https = true

[[services]]
  protocol = "tcp"
  internal_port = 3001

  [[services.ports]]
    port = 443
    handlers = ["tls", "http"]
```

```bash
cd signaling
fly launch
fly secrets set REDIS_URL=redis://...
fly secrets set LARAVEL_URL=https://flowsync-web.fly.dev
fly deploy
```

---

## Redis URL Parsing

For PaaS deployments using `REDIS_URL`, update `signaling/server.js`:

```javascript
// At the top of server.js
const Redis = require('ioredis');

// Parse REDIS_URL if provided, otherwise use individual vars
let redis;
if (process.env.REDIS_URL) {
    redis = new Redis(process.env.REDIS_URL);
} else {
    redis = new Redis({
        host: process.env.REDIS_HOST || '127.0.0.1',
        port: process.env.REDIS_PORT || 6379,
    });
}
```

---

## Cost Comparison

| Provider | Free Tier | Paid Starting |
|----------|-----------|---------------|
| **Railway** | $5 credit/month | $5/month + usage |
| **Render** | 750 hours/month | $7/month |
| **Fly.io** | 3 shared VMs | $1.94/month |

---

## Pros and Cons

### Railway
- **Pros:** Easiest setup, great UI, auto-scaling
- **Cons:** Can get expensive at scale

### Render
- **Pros:** Blueprint YAML, free tier, predictable pricing
- **Cons:** Cold starts on free tier

### Fly.io
- **Pros:** Global edge deployment, low latency
- **Cons:** More complex setup, CLI-focused
