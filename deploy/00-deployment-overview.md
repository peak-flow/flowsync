# FlowSync Deployment Overview

## Architecture Components

FlowSync requires **4 core services** to run in production:

```
┌─────────────────────────────────────────────────────────────────┐
│                         INTERNET                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    REVERSE PROXY (nginx)                         │
│                    - SSL termination                             │
│                    - Route to Laravel (/)                        │
│                    - Route to Signaling (/socket.io)             │
└─────────────────────────────────────────────────────────────────┘
                    │                       │
                    ▼                       ▼
┌───────────────────────────┐   ┌───────────────────────────┐
│     LARAVEL APP           │   │   SIGNALING SERVER        │
│     - PHP 8.4 + FPM       │   │   - Node.js 20.x          │
│     - Web routes          │   │   - Socket.io             │
│     - API routes          │   │   - WebRTC signaling      │
│     - Room management     │   │   - Timer sync            │
└───────────────────────────┘   └───────────────────────────┘
                    │                       │
                    └───────────┬───────────┘
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        DATA LAYER                                │
│  ┌─────────────────────┐    ┌─────────────────────┐             │
│  │    PostgreSQL       │    │      Redis          │             │
│  │    - Rooms          │    │    - Tokens         │             │
│  │    - Messages       │    │    - Timer state    │             │
│  │    - Sessions       │    │    - Presence       │             │
│  └─────────────────────┘    └─────────────────────┘             │
└─────────────────────────────────────────────────────────────────┘
```

## Services Required

| Service | Purpose | Minimum Spec | Recommended |
|---------|---------|--------------|-------------|
| **Web Server** | Laravel app + Signaling | 1 vCPU, 1GB RAM | 2 vCPU, 2GB RAM |
| **PostgreSQL** | Persistent data | 1GB storage | 10GB storage |
| **Redis** | Cache, tokens, realtime state | 256MB | 512MB |
| **Domain + SSL** | HTTPS (required for WebRTC) | 1 domain | 1 domain |

## Deployment Options

### Option A: Single VPS (Simplest)
All services on one server. Good for small teams (<50 concurrent users).

**Providers:** DigitalOcean, Linode, Vultr, Hetzner
**Cost:** ~$6-12/month
**Guide:** [01-server-requirements.md](./01-server-requirements.md), [02-vps-deployment.md](./02-vps-deployment.md)

### Option B: PaaS (Easiest)
Managed services with auto-scaling. Zero server management.

**Providers:** Railway, Render, Fly.io
**Cost:** ~$0-20/month (depending on usage)
**Guide:** [03-paas-deployment.md](./03-paas-deployment.md)

### Option C: Containerized (Most Scalable)
Docker containers with orchestration.

**Providers:** AWS ECS, Google Cloud Run, Kubernetes
**Cost:** Variable
**Guide:** [04-docker-deployment.md](./04-docker-deployment.md)

## Critical Requirements

### 1. HTTPS is Mandatory
WebRTC's `getUserMedia()` API only works on:
- `https://` domains
- `localhost` (development only)

**You MUST have a valid SSL certificate.**

### 2. WebSocket Support
The signaling server uses WebSockets. Your hosting must support:
- Long-lived connections
- WebSocket upgrades
- No aggressive timeouts

### 3. STUN/TURN Servers
For peer connections across NATs:
- **STUN** (included): Google's public STUN servers
- **TURN** (optional): Required if peers are behind strict firewalls

For production with users behind corporate firewalls, consider adding a TURN server:
- [Twilio TURN](https://www.twilio.com/docs/stun-turn) (paid, reliable)
- [coturn](https://github.com/coturn/coturn) (self-hosted, free)
- [metered.ca](https://www.metered.ca/tools/openrelay/) (free tier available)

## Quick Start Decision Tree

```
Do you want to manage servers?
│
├─ NO → Use Railway or Render (Option B)
│       - See: 03-paas-deployment.md
│
└─ YES → Do you need auto-scaling?
         │
         ├─ NO → Single VPS (Option A)
         │       - See: 02-vps-deployment.md
         │
         └─ YES → Docker/Kubernetes (Option C)
                  - See: 04-docker-deployment.md
```

## File Index

| File | Description |
|------|-------------|
| [01-server-requirements.md](./01-server-requirements.md) | Detailed server specifications |
| [02-vps-deployment.md](./02-vps-deployment.md) | Deploy to DigitalOcean/Linode/etc |
| [03-paas-deployment.md](./03-paas-deployment.md) | Deploy to Railway/Render |
| [04-docker-deployment.md](./04-docker-deployment.md) | Docker containerization |
| [05-environment-reference.md](./05-environment-reference.md) | All environment variables |
| [06-ssl-setup.md](./06-ssl-setup.md) | SSL certificate configuration |
| [07-troubleshooting.md](./07-troubleshooting.md) | Common deployment issues |
