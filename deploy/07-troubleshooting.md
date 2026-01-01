# Deployment Troubleshooting Guide

Common issues and solutions when deploying FlowSync.

---

## WebRTC Issues

### Video/Audio Not Working

**Symptom:** Users can join room but see black video or no audio.

**Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| No HTTPS | WebRTC requires HTTPS. Ensure SSL is configured. |
| Camera permissions | User must allow camera/microphone access. |
| STUN servers unreachable | Check firewall allows UDP to Google STUN servers. |
| Browser compatibility | Use Chrome, Firefox, Edge, or Safari (latest). |

**Debug:**
```javascript
// In browser console
navigator.mediaDevices.getUserMedia({video: true, audio: true})
    .then(s => console.log('Media OK', s.getTracks()))
    .catch(e => console.error('Media Error', e));
```

### Peers Can't Connect (ICE Failed)

**Symptom:** "Peer connected" appears but video stays black, or "ICE failed" errors.

**Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Symmetric NAT | Add TURN server (see below) |
| Firewall blocking UDP | Allow UDP ports 10000-60000 outbound |
| Corporate proxy | TURN server with TCP fallback |

**Add TURN Server:**

Update `resources/views/room.blade.php`:
```javascript
config: {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
        // Add TURN server
        {
            urls: 'turn:turn.example.com:3478',
            username: 'user',
            credential: 'password'
        }
    ]
}
```

Free TURN options:
- [Metered TURN](https://www.metered.ca/tools/openrelay/) - Free tier
- [Twilio TURN](https://www.twilio.com/docs/stun-turn) - Pay-as-you-go

---

## WebSocket Issues

### "WebSocket connection failed"

**Symptom:** Browser console shows WebSocket connection errors.

**Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Signaling server not running | `sudo systemctl status flowsync-signaling` |
| Wrong SIGNALING_URL | Check `.env` matches actual URL |
| nginx not proxying | Check `/socket.io/` location block |
| SSL mismatch | HTTPS site needs WSS (wss://) signaling |

**Debug:**
```bash
# Check signaling server
curl -I https://flowsync.example.com/socket.io/

# Expected: 400 Bad Request (Socket.io upgrade required)
# If 404: nginx not routing correctly
# If connection refused: signaling server down
```

### "Invalid token" Error

**Symptom:** User joins room but gets "Invalid room token" error.

**Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Redis prefix mismatch | Check Laravel and Node use same prefix |
| Token expired | Tokens expire in 24h, rejoin room |
| Redis not connected | `redis-cli ping` should return PONG |

**Check Redis keys:**
```bash
redis-cli
KEYS *room*
# Should see: flowsync-database-room:ROOMCODE:token:*
```

---

## Laravel Issues

### 500 Internal Server Error

**Check Laravel logs:**
```bash
tail -f /var/www/flowsync/storage/logs/laravel.log
```

**Common causes:**

| Error | Solution |
|-------|----------|
| APP_KEY missing | `php artisan key:generate` |
| Storage not writable | `chmod -R 775 storage bootstrap/cache` |
| Composer autoload | `composer dump-autoload` |
| Config cached with wrong values | `php artisan config:clear` |

### Database Connection Failed

```bash
# Test PostgreSQL connection
psql -U flowsync -h localhost -d flowsync

# Check Laravel config
php artisan tinker
>>> DB::connection()->getPdo();
```

**Solutions:**

| Error | Solution |
|-------|----------|
| Connection refused | PostgreSQL not running or wrong port |
| Authentication failed | Check DB_USERNAME and DB_PASSWORD |
| Database not found | Create database: `CREATE DATABASE flowsync;` |

### Mixed Content Errors

**Symptom:** Browser blocks HTTP requests from HTTPS page.

**Solution:** Ensure all URLs use HTTPS:
```env
APP_URL=https://flowsync.example.com
SIGNALING_URL=https://flowsync.example.com
```

---

## nginx Issues

### 502 Bad Gateway

**Causes:**
- PHP-FPM not running
- Wrong PHP socket path

**Debug:**
```bash
# Check PHP-FPM
sudo systemctl status php8.4-fpm

# Check socket exists
ls -la /var/run/php/php8.4-fpm.sock

# Check nginx error log
sudo tail -f /var/log/nginx/error.log
```

### 504 Gateway Timeout

**Cause:** PHP script taking too long.

**Solution:** Increase timeouts in nginx:
```nginx
location ~ \.php$ {
    fastcgi_read_timeout 300;
    # ... rest of config
}
```

---

## SSL Issues

### Certificate Errors

```bash
# Check certificate status
sudo certbot certificates

# Renew certificate
sudo certbot renew

# Test renewal
sudo certbot renew --dry-run
```

### Let's Encrypt Rate Limits

If you hit rate limits during testing:
```bash
# Use staging environment
sudo certbot --nginx -d flowsync.example.com --staging

# After testing, remove staging cert and get real one
sudo certbot delete --cert-name flowsync.example.com
sudo certbot --nginx -d flowsync.example.com
```

---

## Redis Issues

### Connection Refused

```bash
# Check Redis running
sudo systemctl status redis-server

# Test connection
redis-cli ping
# Should return: PONG

# Check binding
grep "bind" /etc/redis/redis.conf
# Should be: bind 127.0.0.1
```

### Memory Issues

```bash
# Check memory usage
redis-cli INFO memory

# If maxmemory hit, increase or set eviction policy
# In /etc/redis/redis.conf:
maxmemory 512mb
maxmemory-policy allkeys-lru
```

---

## Docker Issues

### Container Won't Start

```bash
# Check logs
docker compose logs app

# Common issues:
# - Port already in use: change port mapping
# - Volume permissions: check host directory permissions
# - Environment variables: verify .env file exists
```

### Database Connection in Docker

```bash
# From app container, use service name as host
DB_HOST=postgres  # Not localhost or 127.0.0.1

# Test from container
docker compose exec app php artisan tinker
>>> DB::connection()->getPdo();
```

---

## Performance Issues

### Slow Page Load

**Check:**
```bash
# Laravel debug bar (dev only)
# Or check response times in nginx logs

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### High Memory Usage

```bash
# Check PHP-FPM workers
ps aux | grep php-fpm

# Adjust in /etc/php/8.4/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
```

---

## Quick Diagnostic Commands

```bash
# All services status
sudo systemctl status nginx php8.4-fpm postgresql redis-server flowsync-signaling

# Port check
sudo lsof -i :80 -i :443 -i :3001 -i :5432 -i :6379

# Disk space
df -h

# Memory
free -m

# Laravel health
cd /var/www/flowsync
php artisan about

# Test all connections
php artisan tinker
>>> DB::connection()->getPdo(); // Database
>>> Redis::ping(); // Redis
>>> file_put_contents(storage_path('test.txt'), 'test'); // Storage
```

---

## Getting Help

If you're still stuck:

1. **Check logs** - Most issues are logged somewhere
2. **Search error message** - Include "Laravel" or "Socket.io" in search
3. **Laravel Docs** - https://laravel.com/docs
4. **Socket.io Docs** - https://socket.io/docs
5. **SimplePeer Issues** - https://github.com/feross/simple-peer/issues
