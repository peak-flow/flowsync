# Environment Variables Reference

Complete reference for all environment variables in FlowSync.

---

## Laravel Application (.env)

### Application Settings

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_NAME` | Yes | `Laravel` | Application name, used in Redis prefix |
| `APP_ENV` | Yes | `production` | Environment: `local`, `staging`, `production` |
| `APP_KEY` | Yes | - | Encryption key (generate with `php artisan key:generate`) |
| `APP_DEBUG` | Yes | `false` | Debug mode (MUST be `false` in production) |
| `APP_URL` | Yes | - | Full URL: `https://flowsync.example.com` |

### Database (PostgreSQL)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DB_CONNECTION` | Yes | `pgsql` | Database driver |
| `DB_HOST` | Yes | `127.0.0.1` | Database server hostname |
| `DB_PORT` | Yes | `5432` | Database server port |
| `DB_DATABASE` | Yes | `flowsync` | Database name |
| `DB_USERNAME` | Yes | - | Database user |
| `DB_PASSWORD` | Yes | - | Database password |

**Alternative (for PaaS):**
| Variable | Required | Description |
|----------|----------|-------------|
| `DATABASE_URL` | Alt | Full connection string: `postgres://user:pass@host:port/db` |

### Redis

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `REDIS_HOST` | Yes | `127.0.0.1` | Redis server hostname |
| `REDIS_PASSWORD` | No | `null` | Redis password (if required) |
| `REDIS_PORT` | Yes | `6379` | Redis server port |
| `REDIS_PREFIX` | No | `{app_name}_database_` | Key prefix (auto-generated from APP_NAME) |

**Alternative (for PaaS):**
| Variable | Required | Description |
|----------|----------|-------------|
| `REDIS_URL` | Alt | Full connection string: `redis://user:pass@host:port` |

### Cache & Sessions

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `CACHE_STORE` | Yes | `redis` | Cache driver: `redis`, `file`, `database` |
| `SESSION_DRIVER` | Yes | `redis` | Session driver: `redis`, `file`, `database` |
| `SESSION_LIFETIME` | No | `120` | Session lifetime in minutes |
| `QUEUE_CONNECTION` | No | `redis` | Queue driver (if using queues) |

### FlowSync Specific

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SIGNALING_URL` | Yes | - | WebSocket signaling server URL |

**Examples:**
```env
# Development (with ngrok)
SIGNALING_URL=https://abc123.ngrok-free.app

# Production (same domain, proxied)
SIGNALING_URL=https://flowsync.example.com

# Production (separate subdomain)
SIGNALING_URL=https://signal.flowsync.example.com
```

### Logging

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `LOG_CHANNEL` | No | `stack` | Log channel: `stack`, `single`, `stderr` |
| `LOG_LEVEL` | No | `debug` | Minimum log level: `debug`, `info`, `warning`, `error` |

---

## Signaling Server (signaling/.env)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `PORT` | Yes | `3001` | Server listening port |
| `REDIS_HOST` | Yes* | `127.0.0.1` | Redis hostname |
| `REDIS_PORT` | Yes* | `6379` | Redis port |
| `REDIS_URL` | Alt | - | Full Redis URL (for PaaS) |
| `LARAVEL_URL` | Yes | - | Laravel app URL (for CORS) |

*Required unless `REDIS_URL` is provided.

**Examples:**
```env
# Development
PORT=3001
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
LARAVEL_URL=https://flowsync-claude.test

# Production (VPS)
PORT=3001
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
LARAVEL_URL=https://flowsync.example.com

# Production (PaaS with Redis URL)
PORT=3001
REDIS_URL=redis://default:password@redis-host:6379
LARAVEL_URL=https://flowsync-web.railway.app
```

---

## Complete Examples

### Development (.env)

```env
APP_NAME=FlowSync
APP_ENV=local
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=true
APP_URL=https://flowsync-claude.test

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flowsync
DB_USERNAME=flowsync
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

SIGNALING_URL=https://abc123.ngrok-free.app
```

### Production VPS (.env)

```env
APP_NAME=FlowSync
APP_ENV=production
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=false
APP_URL=https://flowsync.example.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flowsync
DB_USERNAME=flowsync
DB_PASSWORD=super-secure-password-here

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

SIGNALING_URL=https://flowsync.example.com
```

### Production Railway (.env)

```env
APP_NAME=FlowSync
APP_ENV=production
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=false
APP_URL=https://flowsync-web.up.railway.app

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DATABASE_URL=${Postgres.DATABASE_URL}
REDIS_URL=${Redis.REDIS_URL}

CACHE_STORE=redis
SESSION_DRIVER=redis

SIGNALING_URL=https://flowsync-signaling.up.railway.app
```

---

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_KEY` is unique and not committed to git
- [ ] Database password is strong and unique
- [ ] Redis password set if exposed to network
- [ ] `.env` file is in `.gitignore`
- [ ] No secrets in `config/*.php` files
- [ ] HTTPS URLs for all public endpoints

---

## Environment Variable Validation

Add to `config/app.php` to validate required variables:

```php
// In AppServiceProvider boot() method:
if (app()->environment('production')) {
    $required = ['APP_KEY', 'DB_DATABASE', 'SIGNALING_URL'];
    foreach ($required as $var) {
        if (empty(env($var))) {
            throw new \RuntimeException("Missing required environment variable: {$var}");
        }
    }
}
```
