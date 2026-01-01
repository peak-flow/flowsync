# Server Requirements

## Minimum Hardware Requirements

### Single Server Deployment

| Resource | Minimum | Recommended | Notes |
|----------|---------|-------------|-------|
| CPU | 1 vCPU | 2 vCPU | Node.js signaling is CPU-light |
| RAM | 1 GB | 2 GB | PHP-FPM workers need memory |
| Storage | 10 GB SSD | 25 GB SSD | Logs, database, uploads |
| Bandwidth | 1 TB/month | Unlimited | Video is P2P, not through server |

### Expected Capacity (Single Server)

| Spec | Concurrent Rooms | Concurrent Users |
|------|------------------|------------------|
| 1 vCPU, 1GB RAM | ~20 rooms | ~100 users |
| 2 vCPU, 2GB RAM | ~50 rooms | ~250 users |
| 4 vCPU, 4GB RAM | ~100 rooms | ~500 users |

> **Note:** Video/audio streams are peer-to-peer and don't pass through the server. Server load is primarily signaling, API calls, and chat persistence.

---

## Software Requirements

### Required Software

| Software | Version | Purpose |
|----------|---------|---------|
| **Ubuntu** | 22.04 LTS or 24.04 LTS | Operating system |
| **PHP** | 8.4.x | Laravel runtime |
| **Composer** | 2.x | PHP dependency management |
| **Node.js** | 20.x LTS | Signaling server |
| **npm** | 10.x | Node dependency management |
| **PostgreSQL** | 15.x or 16.x | Database |
| **Redis** | 7.x | Cache and realtime state |
| **nginx** | 1.24+ | Reverse proxy, SSL termination |
| **Certbot** | Latest | Let's Encrypt SSL certificates |

### PHP Extensions Required

```bash
# Required extensions (most included in PHP 8.4)
php8.4-fpm
php8.4-pgsql      # PostgreSQL driver
php8.4-redis      # Redis driver
php8.4-mbstring   # String handling
php8.4-xml        # XML parsing
php8.4-curl       # HTTP client
php8.4-zip        # Archive handling
php8.4-bcmath     # Arbitrary precision math
php8.4-intl       # Internationalization
```

---

## Network Requirements

### Ports

| Port | Service | Access |
|------|---------|--------|
| 22 | SSH | Admin only (firewall restricted) |
| 80 | HTTP | Public (redirects to HTTPS) |
| 443 | HTTPS | Public |
| 5432 | PostgreSQL | Internal only |
| 6379 | Redis | Internal only |

### Firewall Rules (UFW)

```bash
# Allow SSH (restrict to your IP in production)
ufw allow 22/tcp

# Allow HTTP/HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Enable firewall
ufw enable
```

### DNS Requirements

| Record | Type | Value |
|--------|------|-------|
| `flowsync.yourdomain.com` | A | Server IP |
| `www.flowsync.yourdomain.com` | CNAME | `flowsync.yourdomain.com` |

---

## Database Requirements

### PostgreSQL

**Minimum Configuration:**
```
max_connections = 100
shared_buffers = 256MB
effective_cache_size = 512MB
maintenance_work_mem = 64MB
```

**Storage Estimates:**
| Data | Size per 1000 | Notes |
|------|---------------|-------|
| Rooms | ~50 KB | Metadata only |
| Messages | ~500 KB | Text messages |
| Sessions | ~100 KB | Analytics data |

### Redis

**Minimum Configuration:**
```
maxmemory 256mb
maxmemory-policy allkeys-lru
```

**Key Expiration:**
- Tokens: 24 hours
- Timer state: No expiration (cleaned on room delete)
- Presence: No expiration (cleaned on disconnect)

---

## SSL/TLS Requirements

### Certificate Options

| Option | Cost | Effort | Recommended For |
|--------|------|--------|-----------------|
| **Let's Encrypt** | Free | Low | Most deployments |
| **Cloudflare** | Free | Low | With Cloudflare DNS |
| **Commercial** | $10-100/yr | Medium | Enterprise requirements |

### Required Configuration

```nginx
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers on;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
```

---

## Recommended VPS Providers

### Budget-Friendly

| Provider | Smallest Plan | Price | Notes |
|----------|--------------|-------|-------|
| **Hetzner** | CX22 (2 vCPU, 4GB) | $4.50/mo | Best value, EU/US |
| **Vultr** | Regular (1 vCPU, 1GB) | $6/mo | Global locations |
| **DigitalOcean** | Basic (1 vCPU, 1GB) | $6/mo | Great docs |
| **Linode** | Nanode (1 vCPU, 1GB) | $5/mo | Reliable |

### Managed Database Add-ons

If you prefer managed databases:

| Provider | PostgreSQL | Redis | Notes |
|----------|------------|-------|-------|
| **DigitalOcean** | $15/mo | $15/mo | Easy setup |
| **Railway** | $5/mo | $5/mo | Usage-based |
| **Supabase** | Free tier | - | PostgreSQL only |
| **Upstash** | - | Free tier | Redis only |

---

## Scaling Considerations

### When to Scale

| Symptom | Solution |
|---------|----------|
| High CPU on Laravel | Add PHP-FPM workers or scale horizontally |
| High CPU on Signaling | Horizontal scaling with sticky sessions |
| Database slow | Add read replicas or optimize queries |
| Redis memory full | Increase maxmemory or add cluster |

### Horizontal Scaling Architecture

```
                    Load Balancer
                         │
           ┌─────────────┼─────────────┐
           │             │             │
      ┌────▼────┐   ┌────▼────┐   ┌────▼────┐
      │ Laravel │   │ Laravel │   │ Laravel │
      │   #1    │   │   #2    │   │   #3    │
      └────┬────┘   └────┬────┘   └────┬────┘
           │             │             │
           └─────────────┼─────────────┘
                         │
                 ┌───────▼───────┐
                 │   Shared DB   │
                 │  + Redis      │
                 └───────────────┘
```

For signaling, use Redis pub/sub for cross-instance communication (requires Socket.io Redis adapter).
