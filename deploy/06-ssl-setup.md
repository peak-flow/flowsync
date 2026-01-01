# SSL/TLS Setup Guide

SSL is **mandatory** for FlowSync because WebRTC requires HTTPS to access camera/microphone.

---

## Option A: Let's Encrypt with Certbot (Recommended)

### Prerequisites
- Domain pointing to your server (A record)
- nginx installed and running
- Ports 80 and 443 open

### Install Certbot
```bash
sudo apt update
sudo apt install -y certbot python3-certbot-nginx
```

### Obtain Certificate
```bash
sudo certbot --nginx -d flowsync.example.com
```

Follow prompts:
1. Enter email for renewal notices
2. Agree to terms of service
3. Choose to redirect HTTP to HTTPS (recommended)

### Verify Configuration
```bash
# Check certificate
sudo certbot certificates

# Test SSL
curl -I https://flowsync.example.com
```

### Auto-Renewal
Certbot installs a systemd timer for auto-renewal:
```bash
# Check timer status
sudo systemctl status certbot.timer

# Test renewal
sudo certbot renew --dry-run
```

---

## Option B: Cloudflare SSL

If using Cloudflare DNS and proxy:

### 1. Add Site to Cloudflare
1. Sign up at cloudflare.com
2. Add your domain
3. Update nameservers at your registrar

### 2. Configure SSL Mode
In Cloudflare Dashboard → SSL/TLS:
- Set mode to **Full (strict)** for best security
- Or **Full** if you don't have a valid origin cert

### 3. Origin Certificate (Optional but Recommended)
1. Go to SSL/TLS → Origin Server
2. Create Certificate
3. Download cert and key
4. Install on server:

```bash
sudo mkdir -p /etc/ssl/cloudflare
sudo nano /etc/ssl/cloudflare/cert.pem  # Paste certificate
sudo nano /etc/ssl/cloudflare/key.pem   # Paste private key
sudo chmod 600 /etc/ssl/cloudflare/key.pem
```

Update nginx:
```nginx
server {
    listen 443 ssl http2;
    server_name flowsync.example.com;

    ssl_certificate /etc/ssl/cloudflare/cert.pem;
    ssl_certificate_key /etc/ssl/cloudflare/key.pem;

    # ... rest of config
}
```

---

## Option C: Self-Signed (Development Only)

**Warning:** Only for local testing, not production!

### Generate Certificate
```bash
sudo mkdir -p /etc/ssl/flowsync
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/flowsync/key.pem \
    -out /etc/ssl/flowsync/cert.pem \
    -subj "/CN=flowsync.local"
```

### Configure nginx
```nginx
server {
    listen 443 ssl http2;
    server_name flowsync.local;

    ssl_certificate /etc/ssl/flowsync/cert.pem;
    ssl_certificate_key /etc/ssl/flowsync/key.pem;

    # ... rest of config
}
```

### Trust Certificate (macOS)
```bash
sudo security add-trusted-cert -d -r trustRoot \
    -k /Library/Keychains/System.keychain /etc/ssl/flowsync/cert.pem
```

---

## nginx SSL Configuration

### Recommended SSL Settings

Add to your server block or create `/etc/nginx/snippets/ssl-params.conf`:

```nginx
# Protocols
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;

# Ciphers (modern configuration)
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;

# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;

# Session
ssl_session_timeout 1d;
ssl_session_cache shared:SSL:50m;
ssl_session_tickets off;

# DH Parameters (generate with: openssl dhparam -out /etc/ssl/dhparam.pem 4096)
# ssl_dhparam /etc/ssl/dhparam.pem;

# Security Headers
add_header Strict-Transport-Security "max-age=63072000" always;
add_header X-Frame-Options DENY;
add_header X-Content-Type-Options nosniff;
add_header X-XSS-Protection "1; mode=block";
```

Include in your server block:
```nginx
server {
    listen 443 ssl http2;
    server_name flowsync.example.com;

    include snippets/ssl-params.conf;

    ssl_certificate /etc/letsencrypt/live/flowsync.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/flowsync.example.com/privkey.pem;

    # ... rest of config
}
```

---

## WebSocket SSL (Important!)

The signaling server uses WebSockets. With SSL:

### Option 1: nginx Proxy (Recommended)
Route `/socket.io/` through nginx (already configured in VPS guide):

```nginx
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
```

**Laravel .env:**
```env
SIGNALING_URL=https://flowsync.example.com
```

### Option 2: Separate SSL for Signaling
If signaling is on a different subdomain:

```bash
# Get certificate for signaling subdomain
sudo certbot --nginx -d signal.flowsync.example.com
```

Update signaling server to use SSL (modify `signaling/server.js`):
```javascript
const https = require('https');
const fs = require('fs');

const httpsServer = https.createServer({
    key: fs.readFileSync('/etc/letsencrypt/live/signal.flowsync.example.com/privkey.pem'),
    cert: fs.readFileSync('/etc/letsencrypt/live/signal.flowsync.example.com/fullchain.pem')
});

const io = new Server(httpsServer, { /* ... */ });
httpsServer.listen(3001);
```

---

## Testing SSL

### Online Tools
- [SSL Labs Server Test](https://www.ssllabs.com/ssltest/)
- [Security Headers](https://securityheaders.com/)

### Command Line
```bash
# Check certificate
openssl s_client -connect flowsync.example.com:443 -servername flowsync.example.com

# Check expiry
echo | openssl s_client -connect flowsync.example.com:443 2>/dev/null | openssl x509 -noout -dates

# Check WebSocket SSL
curl -I https://flowsync.example.com/socket.io/
```

---

## Certificate Renewal

### Let's Encrypt Auto-Renewal
```bash
# Certbot timer handles this automatically
# Check timer
sudo systemctl list-timers | grep certbot

# Manual renewal
sudo certbot renew

# Renewal with hook to restart services
sudo certbot renew --deploy-hook "systemctl reload nginx"
```

### Renewal Notifications
Certbot sends emails 20 days before expiry. Ensure:
```bash
# Check registered email
sudo certbot show_account

# Update email
sudo certbot update_account --email new@email.com
```

---

## Troubleshooting SSL

| Issue | Solution |
|-------|----------|
| Certificate not trusted | Check full chain is served, not just cert |
| Mixed content warnings | Ensure all resources use HTTPS |
| WebSocket fails after SSL | Check nginx WebSocket proxy config |
| "NET::ERR_CERT_AUTHORITY_INVALID" | Self-signed cert not trusted by browser |
| Renewal fails | Check port 80 is open, domain resolves |
