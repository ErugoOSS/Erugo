# Erugo Cloud Connect API

**Version:** 1.0.0

API for Erugo Cloud Connect - a service that simplifies domain routing for self-hosted Erugo instances via WireGuard tunnels.

**Base URL:** `https://api.erugo.cloud/v1`

## Table of Contents

### Endpoints

- [Authentication](#authentication)
- [User](#user)
- [Billing](#billing)
- [Instances](#instances)
- [Subdomains](#subdomains)
- [Custom Domains](#custom-domains)
- [Tunnel](#tunnel)

- [Components](#components)

## Endpoints

### Authentication

#### POST `/auth/register`

Register a new user account

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| name | `string` | Yes |  |
| email | `string` | Yes |  |
| password | `string` | Yes |  |
| password_confirmation | `string` | Yes |  |
| accept_terms | `boolean` | Yes |  |
| accept_privacy | `boolean` | Yes |  |
| accept_marketing | `boolean` | No |  |
| erugo_version | `string` | No |  |

**Responses:**

**201**: User created successfully

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| user | `object` | No |  |
| message | `string` | No |  |

**422**: Validation error

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/auth/login`

Authenticate and receive tokens

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| email | `string` | Yes |  |
| password | `string` | Yes |  |

**Responses:**

**200**: Login successful

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| access_token | `string` | No |  |
| refresh_token | `string` | No |  |
| token_type | `string` | No |  |
| expires_in | `integer` | No | Token expiry in seconds |
| user | `object` | No |  |

**401**: Invalid credentials

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/auth/refresh`

Refresh an expired access token

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| refresh_token | `string` | Yes |  |

**Responses:**

**200**: Token refreshed successfully

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| access_token | `string` | No |  |
| expires_in | `integer` | No |  |

**401**: Invalid or expired refresh token

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/auth/forgot-password`

Request password reset email

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| email | `string` | Yes |  |

**Responses:**

**200**: Reset email sent (if account exists)

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| message | `string` | No |  |

---

#### POST `/auth/reset-password`

Reset password with token from email

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| token | `string` | Yes |  |
| password | `string` | Yes |  |
| password_confirmation | `string` | Yes |  |

**Responses:**

**200**: Password reset successfully

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| message | `string` | No |  |

**400**: Invalid or expired token

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/auth/resend-verification`

Resend verification email

Resends the email verification link to the authenticated user. Only works if the email is not already verified.

**Responses:**

**200**: Verification email sent

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| message | `string` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**422**: Email is already verified

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### GET `/auth/verify-email`

Verify email via link (returns HTML page)

Direct verification endpoint for email links. Returns an HTML page showing success or failure status.

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| token | `string` | query | Yes | Email verification token from the verification email |

**Responses:**

**200**: Email verified successfully (HTML page)

Content Type: `text/html`

**Type:** `string`

**400**: Invalid or expired token (HTML page)

Content Type: `text/html`

**Type:** `string`

---

#### POST `/auth/verify-email`

Verify email with token (JSON API)

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| token | `string` | Yes |  |

**Responses:**

**200**: Email verified successfully

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| message | `string` | No |  |

**400**: Invalid or expired token

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

### User

#### GET `/user`

Get current user profile

**Responses:**

**200**: User profile

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | `string` | No |  |
| email | `string` | No |  |
| name | `string` | No |  |
| email_verified | `boolean` | No |  |
| is_admin | `boolean` | No |  |
| account_status | `string` | No |  |
| subscription_status | `string` | No |  |
| subscription_plan | `string` | No | The user's current plan name (e.g., 'free', 'pro', 'business') |
| created_at | `string` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### PATCH `/user`

Update user profile

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| name | `string` | No |  |

**Responses:**

**200**: User updated

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | `string` | No |  |
| email | `string` | No |  |
| name | `string` | No |  |
| email_verified | `boolean` | No |  |
| is_admin | `boolean` | No |  |
| account_status | `string` | No |  |
| subscription_status | `string` | No |  |
| subscription_plan | `string` | No | The user's current plan name (e.g., 'free', 'pro', 'business') |
| created_at | `string` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### GET `/user/usage`

Get account usage statistics

Returns aggregate usage data for the authenticated user, including instance counts, total transfer across all instances, and plan limits.

**Responses:**

**200**: Account usage statistics

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| instances | `object` | No |  |
| transfer | `object` | No |  |
| plan | `object` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

### Billing

#### GET `/billing/plans`

Get available subscription plans

Returns a list of all active subscription plans with their limits and features.

**Responses:**

**200**: List of available plans

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| plans | `object[]` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/billing/checkout`

Create a Stripe Checkout session

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| plan | `string` | Yes |  |

**Responses:**

**200**: Checkout session created

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| checkout_url | `string` | No |  |
| session_id | `string` | No |  |
| poll_interval | `integer` | No | Recommended polling interval in milliseconds |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### GET `/billing/subscription`

Get current subscription details

**Responses:**

**200**: Subscription details

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| status | `string` | No |  |
| plan | `string` | No |  |
| current_period_start | `string` | No |  |
| current_period_end | `string` | No |  |
| cancel_at_period_end | `boolean` | No |  |
| payment_method | `object` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/billing/portal`

Create Stripe Customer Portal session

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| return_url | `string` | Yes |  |

**Responses:**

**200**: Portal session created

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| portal_url | `string` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/billing/webhook`

Handle Stripe webhook events

**Request Body:**

Content Type: `application/json` (required)

**Type:** `object`

**Responses:**

**200**: Webhook processed

**400**: Invalid signature

---

### Instances

#### GET `/instances`

List all instances for the authenticated user

**Responses:**

**200**: List of instances

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| instances | `object[]` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/instances`

Create a new instance

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| name | `string` | Yes |  |
| subdomain | `string` | Yes |  |

**Responses:**

**201**: Instance created

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| instance | `object` | No |  |
| credentials | `object` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**402**: Subscription required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required or instance limit exceeded

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**409**: Subdomain already taken

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### GET `/instances/{instanceId}`

Get instance details

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |

**Responses:**

**200**: Instance details

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | `string` | No |  |
| name | `string` | No |  |
| subdomain | `string` | No |  |
| full_domain | `string` | No |  |
| custom_domain | `string` | No |  |
| status | `string` | No |  |
| tunnel_ip | `string` | No |  |
| last_seen | `string` | No |  |
| created_at | `string` | No |  |
| transfer | `object` | No | Transfer usage data for the current billing period |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### DELETE `/instances/{instanceId}`

Delete an instance

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |

**Responses:**

**204**: Instance deleted

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### PATCH `/instances/{instanceId}`

Update instance settings

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| name | `string` | No |  |
| subdomain | `string` | No |  |

**Responses:**

**200**: Instance updated

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| id | `string` | No |  |
| name | `string` | No |  |
| subdomain | `string` | No |  |
| full_domain | `string` | No |  |
| custom_domain | `string` | No |  |
| status | `string` | No |  |
| tunnel_ip | `string` | No |  |
| last_seen | `string` | No |  |
| created_at | `string` | No |  |
| transfer | `object` | No | Transfer usage data for the current billing period |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**409**: Subdomain already taken

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/instances/{instanceId}/regenerate-token`

Generate a new instance token

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |

**Responses:**

**200**: New token generated

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| instance_token | `string` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

### Subdomains

#### GET `/subdomains/check`

Check if a subdomain is available

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| subdomain | `string` | query | Yes |  |

**Responses:**

**200**: Subdomain availability

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| subdomain | `string` | No |  |
| available | `boolean` | No |  |
| suggestions | `string[]` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

### Custom Domains

#### GET `/instances/{instanceId}/domains`

List custom domains for an instance

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |

**Responses:**

**200**: List of domains

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| domains | `object[]` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/instances/{instanceId}/domains`

Add a custom domain to an instance

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| domain | `string` | Yes |  |

**Responses:**

**201**: Domain added

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| domain | `string` | No |  |
| status | `string` | No |  |
| verification | `object` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required or domain limit exceeded

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**409**: Domain already exists

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/instances/{instanceId}/domains/{domain}/verify`

Trigger DNS verification for a domain

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |
| domain | `string` | path | Yes |  |

**Responses:**

**200**: Verification result

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| domain | `string` | No |  |
| status | `string` | No |  |
| ssl_status | `string` | No |  |
| error | `string` | No |  |

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance or domain not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### DELETE `/instances/{instanceId}/domains/{domain}`

Remove a custom domain

**Parameters:**

| Name | Type | In | Required | Description |
|------|------|----|---------|--------------|
| instanceId | `string` | path | Yes |  |
| domain | `string` | path | Yes |  |

**Responses:**

**204**: Domain removed

**401**: Unauthorized

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**403**: Email verification required

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

**404**: Instance or domain not found

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

### Tunnel

#### POST `/tunnel/register`

Register WireGuard public key and establish tunnel

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| public_key | `string` | Yes | WireGuard public key (base64) |

**Responses:**

**200**: Tunnel registered

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| status | `string` | No |  |
| tunnel_config | `object` | No |  |
| domains | `object` | No |  |

**401**: Invalid instance token

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/tunnel/heartbeat`

Send periodic heartbeat

**Request Body:**

Content Type: `application/json` (required)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| timestamp | `string` | Yes |  |

**Responses:**

**200**: Heartbeat acknowledged

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| status | `string` | No |  |
| next_heartbeat_in | `integer` | No | Seconds until next heartbeat expected |

**401**: Invalid instance token

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### POST `/tunnel/disconnect`

Gracefully disconnect the tunnel

**Responses:**

**200**: Tunnel disconnected

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| status | `string` | No |  |

**401**: Invalid instance token

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

#### GET `/tunnel/status`

Get current tunnel and domain status

**Responses:**

**200**: Tunnel status

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| tunnel_status | `string` | No |  |
| domains | `object` | No |  |

**401**: Invalid instance token

Content Type: `application/json`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| error | `object` | No |  |

---

## Components

### SecuritySchemes

#### bearerAuth

User access token

---

#### instanceAuth

Instance token for tunnel authentication

---

### Schemas

#### RegisterRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| name | `string` | Yes |  |
| email | `string` | Yes |  |
| password | `string` | Yes |  |
| password_confirmation | `string` | Yes |  |
| accept_terms | `boolean` | Yes |  |
| accept_privacy | `boolean` | Yes |  |
| accept_marketing | `boolean` | No |  |
| erugo_version | `string` | No |  |

---

#### RegisterResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| user | `object` | No |  |
| message | `string` | No |  |

---

#### LoginRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| email | `string` | Yes |  |
| password | `string` | Yes |  |

---

#### LoginResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| access_token | `string` | No |  |
| refresh_token | `string` | No |  |
| token_type | `string` | No |  |
| expires_in | `integer` | No | Token expiry in seconds |
| user | `object` | No |  |

---

#### RefreshTokenRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| refresh_token | `string` | Yes |  |

---

#### RefreshTokenResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| access_token | `string` | No |  |
| expires_in | `integer` | No |  |

---

#### ForgotPasswordRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| email | `string` | Yes |  |

---

#### ResetPasswordRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| token | `string` | Yes |  |
| password | `string` | Yes |  |
| password_confirmation | `string` | Yes |  |

---

#### VerifyEmailRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| token | `string` | Yes |  |

---

#### MessageResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| message | `string` | No |  |

---

#### UserBasic

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| id | `string` | No |  |
| name | `string` | No |  |
| email | `string` | No |  |
| created_at | `string` | No |  |
| account_status | `string` | No |  |

---

#### User

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| id | `string` | No |  |
| email | `string` | No |  |
| name | `string` | No |  |
| email_verified | `boolean` | No |  |
| is_admin | `boolean` | No |  |
| account_status | `string` | No |  |
| subscription_status | `string` | No |  |
| subscription_plan | `string` | No | The user's current plan name (e.g., 'free', 'pro', 'business') |
| created_at | `string` | No |  |

---

#### UpdateUserRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| name | `string` | No |  |

---

#### UserUsageResponse

Aggregate account usage statistics

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| instances | `object` | No |  |
| transfer | `object` | No |  |
| plan | `object` | No |  |

---

#### CheckoutRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| plan | `string` | Yes |  |

---

#### CheckoutResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| checkout_url | `string` | No |  |
| session_id | `string` | No |  |
| poll_interval | `integer` | No | Recommended polling interval in milliseconds |

---

#### Subscription

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| status | `string` | No |  |
| plan | `string` | No |  |
| current_period_start | `string` | No |  |
| current_period_end | `string` | No |  |
| cancel_at_period_end | `boolean` | No |  |
| payment_method | `object` | No |  |

---

#### Plan

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| name | `string` | No | Plan identifier (lowercase, e.g., 'free', 'pro', 'business') |
| display_name | `string` | No | Human-readable plan name |
| is_free | `boolean` | No | True if this is a free plan (no checkout required) |
| max_instances | `integer` | No | Maximum number of instances allowed |
| max_domains_per_instance | `integer` | No | Maximum custom domains per instance |
| custom_domains_allowed | `boolean` | No | Whether custom domains are enabled for this plan |
| max_bandwidth_mbps | `integer` | No | Maximum bandwidth in Mbps (null = unlimited) |
| max_transfer_gb | `integer` | No | Maximum monthly transfer in GB (null = unlimited) |

---

#### PlansResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| plans | `object[]` | No |  |

---

#### PortalRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| return_url | `string` | Yes |  |

---

#### PortalResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| portal_url | `string` | No |  |

---

#### Instance

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| id | `string` | No |  |
| name | `string` | No |  |
| subdomain | `string` | No |  |
| full_domain | `string` | No |  |
| custom_domain | `string` | No |  |
| status | `string` | No |  |
| tunnel_ip | `string` | No |  |
| last_seen | `string` | No |  |
| created_at | `string` | No |  |
| transfer | `object` | No | Transfer usage data for the current billing period |

---

#### InstanceTransfer

Transfer usage data for the current billing period

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| bytes_in | `integer` | No | Total bytes received by this instance |
| bytes_out | `integer` | No | Total bytes sent by this instance |
| bytes_total | `integer` | No | Total bytes transferred (in + out) |
| period_start | `string` | No | Start of the current billing period |

---

#### InstanceListResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| instances | `object[]` | No |  |

---

#### CreateInstanceRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| name | `string` | Yes |  |
| subdomain | `string` | Yes |  |

---

#### CreateInstanceResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| instance | `object` | No |  |
| credentials | `object` | No |  |

---

#### UpdateInstanceRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| name | `string` | No |  |
| subdomain | `string` | No |  |

---

#### RegenerateTokenResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| instance_token | `string` | No |  |

---

#### SubdomainCheckResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| subdomain | `string` | No |  |
| available | `boolean` | No |  |
| suggestions | `string[]` | No |  |

---

#### CustomDomain

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| domain | `string` | No |  |
| status | `string` | No |  |
| ssl_status | `string` | No |  |
| verified_at | `string` | No |  |

---

#### DomainListResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| domains | `object[]` | No |  |

---

#### AddDomainRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| domain | `string` | Yes |  |

---

#### AddDomainResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| domain | `string` | No |  |
| status | `string` | No |  |
| verification | `object` | No |  |

---

#### VerifyDomainResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| domain | `string` | No |  |
| status | `string` | No |  |
| ssl_status | `string` | No |  |
| error | `string` | No |  |

---

#### TunnelRegisterRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| public_key | `string` | Yes | WireGuard public key (base64) |

---

#### TunnelRegisterResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| status | `string` | No |  |
| tunnel_config | `object` | No |  |
| domains | `object` | No |  |

---

#### HeartbeatRequest

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| timestamp | `string` | Yes |  |

---

#### HeartbeatResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| status | `string` | No |  |
| next_heartbeat_in | `integer` | No | Seconds until next heartbeat expected |

---

#### DisconnectResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| status | `string` | No |  |

---

#### TunnelStatusResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| tunnel_status | `string` | No |  |
| domains | `object` | No |  |

---

#### ErrorResponse

**Properties:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| error | `object` | No |  |

---

