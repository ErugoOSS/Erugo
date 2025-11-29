# Erugo Cloud Connect

## Overview

Erugo Cloud Connect is a paid service that simplifies domain routing for self-hosted Erugo instances. Users can connect their Docker-hosted Erugo to our cloud infrastructure via a WireGuard tunnel, eliminating the need to configure domains, SSL certificates, or expose ports.

### Value Proposition

- **No port forwarding required** - Works behind NAT, firewalls, CGNAT
- **Automatic SSL** - Let's Encrypt certificates managed by us
- **Custom domains** - Users can use their own domain or a provided subdomain
- **Zero network configuration** - Tunnel activates from the Erugo admin panel

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         ERUGO CLOUD INFRASTRUCTURE                          │
│                                                                             │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │   Caddy     │    │  WireGuard  │    │  Cloud API  │    │  PostgreSQL │  │
│  │  (Reverse   │◄──►│   Server    │    │  (Auth,     │◄──►│  (Users,    │  │
│  │   Proxy)    │    │             │    │   Billing,  │    │   Tunnels,  │  │
│  │             │    │             │    │   Tunnels)  │    │   Domains)  │  │
│  └─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘  │
│        ▲                   ▲                  ▲                             │
│        │                   │                  │                             │
└────────┼───────────────────┼──────────────────┼─────────────────────────────┘
         │                   │                  │
   HTTPS Traffic      WireGuard UDP       REST API (HTTPS)
   (*.erugo.cloud)      (Port 51820)            │
         │                   │                  │
┌────────┼───────────────────┼──────────────────┼─────────────────────────────┐
│        ▼                   ▼                  ▼                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                    CUSTOMER'S DOCKER CONTAINER                       │   │
│  │                                                                      │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐   │   │
│  │  │    Erugo     │  │     tusd     │  │   WireGuard Client       │   │   │
│  │  │  Laravel App │  │   (uploads)  │  │   + Cloud Connect Agent  │   │   │
│  │  └──────────────┘  └──────────────┘  └──────────────────────────┘   │   │
│  │                                                                      │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                           CUSTOMER'S NETWORK                                │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Network Isolation

Each customer gets a unique IP on the WireGuard network (e.g., `10.100.0.X/32`). Customers cannot see or communicate with each other's networks - they can only reach the cloud server.

---

## Cloud API Specification

### Base URL
```
https://api.erugo.cloud/v1
```

### Authentication

Two types of authentication:

1. **User Authentication** - For web dashboard, account management
   - JWT tokens issued on login
   - Short-lived access tokens (15 min) + refresh tokens (7 days)

2. **Instance Authentication** - For Erugo containers connecting to the API
   - Instance tokens (long-lived JWT) generated in dashboard
   - Used by containers to configure tunnels and report status

---

## API Endpoints

### Public Endpoints (No Auth)

#### `POST /auth/register`
Create a new user account.

**Request:**
```json
{
  "name": "John Doe",
  "email": "user@example.com",
  "password": "securepassword",
  "password_confirmation": "securepassword",
  "accept_terms": true,
  "accept_privacy": true,
  "accept_marketing": true,
  "erugo_version": "1.0.0"
}
```

**Response:** `201 Created`
```json
{
  "user": {
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "name": "John Doe",
    "email": "user@example.com",
    "created_at": "2025-11-29T12:00:00Z",
    "account_status": "pending_email_verification"
  },
  "message": "Verification email sent"
}
```

---

#### `POST /auth/login`
Authenticate and receive tokens.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "securepassword"
}
```

**Response:** `200 OK`
```json
{
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "token_type": "Bearer",
  "expires_in": 900,
  "user": {
    "id": "usr_abc123",
    "email": "user@example.com",
    "name": "John Doe",
    "subscription_status": "active",
    "subscription_plan": "pro"
  }
}
```

---

#### `POST /auth/refresh`
Refresh an expired access token.

**Request:**
```json
{
  "refresh_token": "eyJhbG..."
}
```

**Response:** `200 OK`
```json
{
  "access_token": "eyJhbG...",
  "expires_in": 900
}
```

---

#### `POST /auth/forgot-password`
Request password reset email.

**Request:**
```json
{
  "email": "user@example.com"
}
```

**Response:** `200 OK`
```json
{
  "message": "If an account exists, a reset email has been sent"
}
```

---

#### `POST /auth/reset-password`
Reset password with token from email.

**Request:**
```json
{
  "token": "reset_token_from_email",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

**Response:** `200 OK`
```json
{
  "message": "Password reset successfully"
}
```

---

### User Authenticated Endpoints

*Requires `Authorization: Bearer <access_token>` header*

#### `GET /user`
Get current user profile.

**Response:** `200 OK`
```json
{
  "id": "usr_abc123",
  "email": "user@example.com",
  "name": "John Doe",
  "subscription_status": "active",
  "subscription_plan": "pro",
  "created_at": "2025-11-29T12:00:00Z"
}
```

---

#### `PATCH /user`
Update user profile.

**Request:**
```json
{
  "name": "John Smith"
}
```

**Response:** `200 OK`
```json
{
  "id": "usr_abc123",
  "email": "user@example.com",
  "name": "John Smith"
}
```

---

### Billing Endpoints

#### `POST /billing/checkout`
Create a Stripe Checkout session for subscription.

The server controls the redirect URLs - they point to pages on the cloud dashboard that display success/cancel messages. The Erugo container opens this URL in a new tab and polls `/billing/subscription` to detect when payment completes.

**Request:**
```json
{
  "plan": "pro"
}
```

**Response:** `200 OK`
```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_xxx",
  "session_id": "cs_xxx",
  "poll_interval": 3000
}
```

---

#### `GET /billing/subscription`
Get current subscription details.

**Response:** `200 OK`
```json
{
  "status": "active",
  "plan": "pro",
  "current_period_start": "2025-11-01T00:00:00Z",
  "current_period_end": "2025-12-01T00:00:00Z",
  "cancel_at_period_end": false,
  "payment_method": {
    "brand": "visa",
    "last4": "4242"
  }
}
```

---

#### `POST /billing/portal`
Create Stripe Customer Portal session for managing subscription.

**Request:**
```json
{
  "return_url": "https://dashboard.erugo.cloud/settings"
}
```

**Response:** `200 OK`
```json
{
  "portal_url": "https://billing.stripe.com/p/session/xxx"
}
```

---

#### `POST /billing/webhook` (Stripe Webhook - No Auth)
Handle Stripe webhook events.

*Verified via Stripe signature header*

**Events to handle:**
- `checkout.session.completed` - Activate subscription
- `customer.subscription.updated` - Update subscription status
- `customer.subscription.deleted` - Deactivate subscription
- `invoice.payment_failed` - Handle failed payments

---

#### Stripe Redirect Pages (Cloud Dashboard)

The cloud dashboard needs simple static-ish pages for Stripe redirects:

**`GET /billing/success`** - Displayed after successful checkout
```
"Payment successful! You can close this tab and return to your Erugo admin panel."
```

**`GET /billing/cancel`** - Displayed if user cancels checkout
```
"Payment cancelled. You can close this tab and try again from your Erugo admin panel."
```

These pages exist because Stripe requires redirect URLs, but the actual state change is detected by the Erugo container polling the API.

---

### Instance Management Endpoints

#### `GET /instances`
List all instances for the authenticated user.

**Response:** `200 OK`
```json
{
  "instances": [
    {
      "id": "inst_xyz789",
      "name": "My Erugo Server",
      "subdomain": "myfiles",
      "custom_domain": null,
      "status": "connected",
      "tunnel_ip": "10.100.0.42",
      "last_seen": "2025-11-29T14:30:00Z",
      "created_at": "2025-11-29T12:00:00Z"
    }
  ]
}
```

---

#### `POST /instances`
Create a new instance and get connection credentials.

**Request:**
```json
{
  "name": "My Erugo Server",
  "subdomain": "myfiles"
}
```

**Response:** `201 Created`
```json
{
  "instance": {
    "id": "inst_xyz789",
    "name": "My Erugo Server",
    "subdomain": "myfiles",
    "full_domain": "myfiles.erugo.cloud",
    "status": "pending",
    "tunnel_ip": "10.100.0.42",
    "created_at": "2025-11-29T12:00:00Z"
  },
  "credentials": {
    "instance_token": "eyJhbG...",
    "wireguard": {
      "server_public_key": "1y8JgBgNarojHK12KYbAAy+7HqZq2HGsE0Qw24toBXo=",
      "server_endpoint": "connect.erugo.cloud:51820",
      "client_ip": "10.100.0.42/32",
      "allowed_ips": "10.100.0.0/24"
    }
  }
}
```

**Note:** The `instance_token` is shown only once at creation. User must save it.

---

#### `GET /instances/{instance_id}`
Get instance details.

**Response:** `200 OK`
```json
{
  "id": "inst_xyz789",
  "name": "My Erugo Server",
  "subdomain": "myfiles",
  "full_domain": "myfiles.erugo.cloud",
  "custom_domain": null,
  "status": "connected",
  "tunnel_ip": "10.100.0.42",
  "last_seen": "2025-11-29T14:30:00Z",
  "created_at": "2025-11-29T12:00:00Z"
}
```

---

#### `PATCH /instances/{instance_id}`
Update instance settings.

**Request:**
```json
{
  "name": "Production Server",
  "subdomain": "files"
}
```

**Response:** `200 OK`
```json
{
  "id": "inst_xyz789",
  "name": "Production Server",
  "subdomain": "files",
  "full_domain": "files.erugo.cloud"
}
```

---

#### `DELETE /instances/{instance_id}`
Delete an instance and revoke tunnel access.

**Response:** `204 No Content`

---

#### `POST /instances/{instance_id}/regenerate-token`
Generate a new instance token (invalidates the old one).

**Response:** `200 OK`
```json
{
  "instance_token": "eyJhbG..."
}
```

---

### Subdomain Endpoints

#### `GET /subdomains/check`
Check if a subdomain is available.

**Query Parameters:**
- `subdomain` - The subdomain to check

**Response:** `200 OK`
```json
{
  "subdomain": "myfiles",
  "available": true
}
```

Or if taken:
```json
{
  "subdomain": "myfiles",
  "available": false,
  "suggestions": ["myfiles1", "myfiles-share", "myfiles2025"]
}
```

---

### Custom Domain Endpoints

#### `POST /instances/{instance_id}/domains`
Add a custom domain to an instance.

**Request:**
```json
{
  "domain": "share.example.com"
}
```

**Response:** `201 Created`
```json
{
  "domain": "share.example.com",
  "status": "pending_verification",
  "verification": {
    "type": "CNAME",
    "name": "share",
    "value": "inst_xyz789.tunnel.erugo.cloud",
    "instructions": "Add this CNAME record to your DNS provider"
  }
}
```

---

#### `GET /instances/{instance_id}/domains`
List custom domains for an instance.

**Response:** `200 OK`
```json
{
  "domains": [
    {
      "domain": "share.example.com",
      "status": "verified",
      "ssl_status": "active",
      "verified_at": "2025-11-29T13:00:00Z"
    }
  ]
}
```

---

#### `POST /instances/{instance_id}/domains/{domain}/verify`
Trigger DNS verification check for a domain.

**Response:** `200 OK`
```json
{
  "domain": "share.example.com",
  "status": "verified",
  "ssl_status": "provisioning"
}
```

Or if not yet configured:
```json
{
  "domain": "share.example.com",
  "status": "pending_verification",
  "error": "CNAME record not found"
}
```

---

#### `DELETE /instances/{instance_id}/domains/{domain}`
Remove a custom domain.

**Response:** `204 No Content`

---

### Instance Authenticated Endpoints

*Requires `Authorization: Bearer <instance_token>` header*

These endpoints are called by the Erugo container, not the user dashboard.

#### `POST /tunnel/register`
Register the instance's WireGuard public key and establish tunnel.

**Request:**
```json
{
  "public_key": "Vo/MxSorIa0fdOOC9XpTr7lpn+zSIO8Ur2sZkJkYuHI="
}
```

**Response:** `200 OK`
```json
{
  "status": "registered",
  "tunnel_config": {
    "interface": {
      "address": "10.100.0.42/32"
    },
    "peer": {
      "public_key": "1y8JgBgNarojHK12KYbAAy+7HqZq2HGsE0Qw24toBXo=",
      "endpoint": "connect.erugo.cloud:51820",
      "allowed_ips": "10.100.0.0/24",
      "persistent_keepalive": 25
    }
  },
  "domains": {
    "subdomain": "myfiles.erugo.cloud",
    "custom_domains": ["share.example.com"]
  }
}
```

---

#### `POST /tunnel/heartbeat`
Send periodic heartbeat to indicate instance is alive.

**Request:**
```json
{
  "timestamp": "2025-11-29T14:30:00Z"
}
```

**Response:** `200 OK`
```json
{
  "status": "ok",
  "next_heartbeat_in": 60
}
```

---

#### `POST /tunnel/disconnect`
Gracefully disconnect the tunnel.

**Response:** `200 OK`
```json
{
  "status": "disconnected"
}
```

---

#### `GET /tunnel/status`
Get current tunnel and domain status.

**Response:** `200 OK`
```json
{
  "tunnel_status": "connected",
  "domains": {
    "subdomain": {
      "domain": "myfiles.erugo.cloud",
      "status": "active"
    },
    "custom_domains": [
      {
        "domain": "share.example.com",
        "status": "active",
        "ssl_status": "active"
      }
    ]
  }
}
```

---

## Subscription Plans

| Feature | Free | Pro ($5/mo) | Business ($15/mo) |
|---------|------|-------------|-------------------|
| Instances | 1 | 3 | 10 |
| Subdomain | ✅ | ✅ | ✅ |
| Custom Domain | ❌ | 1 per instance | 5 per instance |
| Support | Community | Email | Priority |

---

## User Flows

### Flow 1: New User Signup & Subscription (from Erugo Admin Panel)

```
1. User opens Cloud Connect settings in Erugo admin panel
2. User clicks "Create Account" or "Sign In"
3. POST /auth/register or POST /auth/login
4. User verifies email (if new account)
5. Erugo receives tokens, stores them
6. Erugo checks subscription status: GET /billing/subscription
7. If no subscription, user clicks "Subscribe"
8. POST /billing/checkout → returns checkout_url
9. Erugo opens checkout_url in NEW TAB (window.open)
10. User completes payment in Stripe Checkout
11. Stripe redirects to cloud dashboard success page (just a "you can close this tab" message)
12. Meanwhile, Erugo polls GET /billing/subscription every 3 seconds
13. When subscription becomes active, Erugo updates UI
14. User can now create instance and connect tunnel
```

### Flow 2: Connecting Erugo Instance (Container Side)

```
1. Admin enters instance_token in Erugo settings
2. Erugo stores token securely
3. Erugo generates WireGuard keypair
4. POST /tunnel/register with public_key
5. API returns tunnel configuration
6. Erugo writes WireGuard config
7. Erugo brings up WireGuard interface
8. Erugo starts heartbeat loop
9. Connection status shown in admin panel
```

### Flow 3: Adding Custom Domain

```
1. User logged into dashboard
2. User navigates to instance settings
3. User clicks "Add Custom Domain"
4. POST /instances/{id}/domains with domain
5. API returns CNAME instructions
6. User adds CNAME record at their DNS provider
7. User clicks "Verify"
8. POST /instances/{id}/domains/{domain}/verify
9. API checks DNS, provisions SSL
10. Domain becomes active
```

---

## Container-Side Implementation

### New Settings (Database)

```php
// Settings group: system.cloud_connect
'cloud_connect_enabled' => 'false',
'cloud_connect_instance_token' => null,      // Encrypted
'cloud_connect_api_url' => 'https://api.erugo.cloud/v1',
'cloud_connect_private_key' => null,         // Generated locally, encrypted
'cloud_connect_public_key' => null,
'cloud_connect_status' => 'disconnected',    // disconnected, connecting, connected, error
'cloud_connect_last_error' => null,
'cloud_connect_subdomain' => null,
'cloud_connect_custom_domains' => '[]',      // JSON array
```

### New Files Required

#### Backend (Laravel)

```
app/
├── Http/
│   └── Controllers/
│       └── CloudConnectController.php    # API endpoints for frontend
├── Services/
│   └── CloudConnectService.php           # Business logic, API calls
├── Jobs/
│   ├── CloudConnectHeartbeat.php         # Periodic heartbeat job
│   └── CloudConnectTunnel.php            # Manage WireGuard tunnel
└── Console/
    └── Commands/
        └── CloudConnectCommand.php       # Artisan command for tunnel management
```

#### Frontend (Vue)

```
resources/js/
└── components/
    └── settings/
        └── cloudConnect.vue              # Settings panel UI
```

### API Routes (Container)

```php
// routes/api.php

// Cloud Connect management (admin only)
Route::middleware(['auth:api', 'admin'])->prefix('cloud-connect')->group(function () {
    Route::get('/status', [CloudConnectController::class, 'status']);
    Route::post('/connect', [CloudConnectController::class, 'connect']);
    Route::post('/disconnect', [CloudConnectController::class, 'disconnect']);
    Route::post('/test', [CloudConnectController::class, 'test']);
});
```

### Docker Changes

#### Dockerfile Additions

```dockerfile
# Add WireGuard tools
RUN apk add --no-cache wireguard-tools
```

#### Docker Compose (User Documentation)

Users must add these to their docker-compose.yml to enable tunnel support:

```yaml
services:
  app:
    image: wardy784/erugo:latest
    cap_add:
      - NET_ADMIN
    devices:
      - /dev/net/tun:/dev/net/tun
```

### Supervisor Configuration

Add optional WireGuard management process:

```ini
[program:erugo-cloud-connect]
command=php /var/www/html/artisan cloud-connect:run
autostart=false
autorestart=true
user=root
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
```

---

## Security Considerations

### Cloud Side

1. **Rate limiting** on all endpoints
2. **Instance tokens** are JWTs with:
   - Instance ID claim
   - User ID claim
   - Issued-at timestamp
   - No expiration (revoked by regenerating)
3. **WireGuard peer isolation** - iptables rules block peer-to-peer traffic
4. **Stripe webhook signature verification**
5. **HTTPS everywhere**

### Container Side

1. **Instance token stored encrypted** in database
2. **Private key never leaves container** - generated locally
3. **API communication over HTTPS only**
4. **Tunnel only routes to cloud server** (AllowedIPs restricted)

---

## Error Handling

### API Error Response Format

```json
{
  "error": {
    "code": "SUBDOMAIN_TAKEN",
    "message": "This subdomain is already in use",
    "details": {
      "subdomain": "myfiles",
      "suggestions": ["myfiles1", "myfiles2"]
    }
  }
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `UNAUTHORIZED` | 401 | Invalid or expired token |
| `FORBIDDEN` | 403 | Valid token but insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `SUBDOMAIN_TAKEN` | 409 | Subdomain already in use |
| `DOMAIN_ALREADY_EXISTS` | 409 | Domain already added |
| `SUBSCRIPTION_REQUIRED` | 402 | Active subscription required |
| `LIMIT_EXCEEDED` | 403 | Plan limit reached (instances, domains) |
| `VALIDATION_ERROR` | 422 | Invalid request data |
| `DNS_VERIFICATION_FAILED` | 400 | Domain DNS not configured correctly |

---

## Webhook Events (Cloud → Container)

Future consideration: Allow cloud to push updates to containers via the tunnel.

Potential events:
- Domain verified
- Subscription status changed
- Forced disconnect (abuse, non-payment)

---

## Monitoring & Observability

### Metrics to Track

- Active tunnels count
- Tunnel connection duration
- API request latency
- Failed connection attempts
- Subscription conversion rate
- Churn rate

### Health Checks

- WireGuard server status
- Caddy status
- API health endpoint
- Database connectivity
- Stripe webhook processing

---

## Future Enhancements

1. **Multiple tunnel servers** - Geographic distribution for lower latency
2. **Bandwidth monitoring** - Usage-based billing tier
3. **Custom SSL certificates** - Bring your own cert option
4. **Team accounts** - Multiple users per account
5. **API access** - Programmatic instance management
6. **Backup tunnels** - Failover to secondary server

