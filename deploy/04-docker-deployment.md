# Docker Deployment Guide

Containerized deployment for FlowSync using Docker and Docker Compose.

---

## Quick Start

### Prerequisites
- Docker 24.x+
- Docker Compose 2.x+

### Deploy with Docker Compose

```bash
# Clone repository
git clone https://github.com/your-repo/flowsync.git
cd flowsync

# Copy environment file
cp .env.example .env.docker
# Edit .env.docker with your production values

# Build and start
docker compose up -d --build

# Run migrations
docker compose exec app php artisan migrate --force
```

---

## Docker Configuration Files

### Dockerfile (Laravel)

Create `Dockerfile` in project root:

```dockerfile
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    postgresql-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install pdo_pgsql mbstring zip bcmath intl opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Install and build frontend
RUN npm ci && npm run build && rm -rf node_modules

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### Dockerfile for Signaling Server

Create `signaling/Dockerfile`:

```dockerfile
FROM node:20-alpine

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --omit=dev

# Copy source
COPY . .

EXPOSE 3001

CMD ["node", "server.js"]
```

### Docker Compose

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: flowsync-app
    restart: unless-stopped
    ports:
      - "80:80"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    env_file:
      - .env.docker
    depends_on:
      - postgres
      - redis
    networks:
      - flowsync

  signaling:
    build:
      context: ./signaling
      dockerfile: Dockerfile
    container_name: flowsync-signaling
    restart: unless-stopped
    ports:
      - "3001:3001"
    environment:
      - PORT=3001
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - LARAVEL_URL=http://app
    depends_on:
      - redis
    networks:
      - flowsync

  postgres:
    image: postgres:16-alpine
    container_name: flowsync-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: flowsync
      POSTGRES_USER: flowsync
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - flowsync

  redis:
    image: redis:7-alpine
    container_name: flowsync-redis
    restart: unless-stopped
    command: redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    networks:
      - flowsync

volumes:
  postgres_data:
  redis_data:

networks:
  flowsync:
    driver: bridge
```

---

## Supporting Configuration Files

### docker/nginx.conf

```nginx
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;
    sendfile on;
    keepalive_timeout 65;
    gzip on;

    server {
        listen 80;
        server_name _;
        root /var/www/html/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
```

### docker/supervisord.conf

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=/usr/local/sbin/php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### docker/php.ini

```ini
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
max_execution_time = 30

opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

---

## Production with Traefik (SSL)

For automatic SSL with Traefik reverse proxy:

### docker-compose.prod.yml

```yaml
version: '3.8'

services:
  traefik:
    image: traefik:v3.0
    container_name: traefik
    restart: unless-stopped
    command:
      - "--api.dashboard=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
      - "--certificatesresolvers.letsencrypt.acme.email=your@email.com"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
      - "--entrypoints.web.http.redirections.entrypoint.to=websecure"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik_certs:/letsencrypt
    networks:
      - flowsync

  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: flowsync-app
    restart: unless-stopped
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.flowsync.rule=Host(`flowsync.example.com`)"
      - "traefik.http.routers.flowsync.entrypoints=websecure"
      - "traefik.http.routers.flowsync.tls.certresolver=letsencrypt"
      - "traefik.http.services.flowsync.loadbalancer.server.port=80"
    env_file:
      - .env.docker
    depends_on:
      - postgres
      - redis
    networks:
      - flowsync

  signaling:
    build:
      context: ./signaling
      dockerfile: Dockerfile
    container_name: flowsync-signaling
    restart: unless-stopped
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.signaling.rule=Host(`flowsync.example.com`) && PathPrefix(`/socket.io`)"
      - "traefik.http.routers.signaling.entrypoints=websecure"
      - "traefik.http.routers.signaling.tls.certresolver=letsencrypt"
      - "traefik.http.services.signaling.loadbalancer.server.port=3001"
    environment:
      - PORT=3001
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - LARAVEL_URL=https://flowsync.example.com
    depends_on:
      - redis
    networks:
      - flowsync

  postgres:
    image: postgres:16-alpine
    container_name: flowsync-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: flowsync
      POSTGRES_USER: flowsync
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - flowsync

  redis:
    image: redis:7-alpine
    container_name: flowsync-redis
    restart: unless-stopped
    command: redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    networks:
      - flowsync

volumes:
  postgres_data:
  redis_data:
  traefik_certs:

networks:
  flowsync:
    driver: bridge
```

### Deploy Production

```bash
# Start with production compose file
docker compose -f docker-compose.prod.yml up -d --build

# Run migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Check logs
docker compose -f docker-compose.prod.yml logs -f
```

---

## Useful Commands

```bash
# View logs
docker compose logs -f app
docker compose logs -f signaling

# Shell into container
docker compose exec app sh
docker compose exec signaling sh

# Run artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# Rebuild single service
docker compose build app
docker compose up -d app

# Full restart
docker compose down
docker compose up -d --build

# View resource usage
docker stats
```

---

## Health Checks

Add health checks to `docker-compose.yml`:

```yaml
services:
  app:
    # ... existing config ...
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

  signaling:
    # ... existing config ...
    healthcheck:
      test: ["CMD", "node", "-e", "require('http').get('http://localhost:3001', (r) => process.exit(r.statusCode === 404 ? 0 : 1))"]
      interval: 30s
      timeout: 10s
      retries: 3

  postgres:
    # ... existing config ...
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U flowsync"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    # ... existing config ...
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
```
