# Erugo Users API

API specification for the App API user management endpoints. All endpoints require admin authentication.

**Base URL:** `/api/app/v1`

**Required Headers:**
```
Authorization: Bearer <access_token>
Content-Type: application/json
Accept: application/json
```

---

## Common Response Formats

### Success Response

```json
{
    "status": "success",
    "data": { ... }
}
```

### Validation Error Response (HTTP 422)

```json
{
    "message": "The email field must be a valid email address.",
    "errors": {
        "field_name": [
            "Error message for this field."
        ]
    }
}
```

### Not Found Response (HTTP 404)

```json
{
    "status": "error",
    "code": "USER_NOT_FOUND",
    "message": "User not found"
}
```

### Authentication Error Response (HTTP 401)

```json
{
    "message": "Unauthenticated."
}
```

---

## List Users

### GET /users

List all non-guest users with pagination.

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | `Int` | 20 | Items per page (max 100) |
| `page` | `Int` | 1 | Page number |

**Response:**

```json
{
    "status": "success",
    "data": {
        "users": [
            {
                "id": 1,
                "name": "Dean Ward",
                "email": "dean@example.com",
                "admin": true,
                "active": true,
                "must_change_password": false,
                "created_at": "2025-12-17T13:40:20+00:00",
                "updated_at": "2025-12-17T13:40:20+00:00"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 1,
            "total_items": 2,
            "per_page": 20
        }
    }
}
```

---

## Get User

### GET /users/{id}

Get a single user by ID.

**Response:**

```json
{
    "status": "success",
    "data": {
        "user": {
            "id": 1,
            "name": "Dean Ward",
            "email": "dean@example.com",
            "admin": true,
            "active": true,
            "must_change_password": false,
            "created_at": "2025-12-17T13:40:20+00:00",
            "updated_at": "2025-12-17T13:40:20+00:00"
        }
    }
}
```

---

## Create User

### POST /users

Create a new user. A random password is auto-generated and an account creation email with password reset link is sent to the user.

**Request Body:**

```json
{
    "email": "user@example.com",
    "name": "New User",
    "admin": false
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `email` | Required, valid email, unique |
| `name` | Required, string, max 255 characters |
| `admin` | Optional, boolean (defaults to false) |

**Success Response (HTTP 201):**

```json
{
    "status": "success",
    "message": "User created successfully",
    "data": {
        "user": {
            "id": 5,
            "name": "New User",
            "email": "user@example.com",
            "admin": false,
            "active": true,
            "must_change_password": false,
            "created_at": "2026-02-02T09:04:42+00:00",
            "updated_at": "2026-02-02T09:04:42+00:00"
        }
    }
}
```

---

## Update User

### PUT /users/{id}

Update a user. Supports partial updates - only include fields you want to change.

**Request Body:**

```json
{
    "name": "Updated Name",
    "admin": true,
    "must_change_password": true
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `email` | Optional, valid email, unique (excluding current user) |
| `name` | Optional, string, max 255 characters |
| `admin` | Optional, boolean |
| `must_change_password` | Optional, boolean |

**Success Response:**

```json
{
    "status": "success",
    "message": "User updated successfully",
    "data": {
        "user": {
            "id": 5,
            "name": "Updated Name",
            "email": "user@example.com",
            "admin": true,
            "active": true,
            "must_change_password": true,
            "created_at": "2026-02-02T09:04:42+00:00",
            "updated_at": "2026-02-02T09:04:48+00:00"
        }
    }
}
```

---

## Delete User

### DELETE /users/{id}

Delete a user. Cannot delete yourself. Cleans up all user data including shares, files, and downloads.

**Safety Check:** Returns error if attempting to delete your own account.

**Success Response:**

```json
{
    "status": "success",
    "message": "User deleted successfully"
}
```

**Self-Delete Error Response (HTTP 400):**

```json
{
    "status": "error",
    "code": "CANNOT_DELETE_SELF",
    "message": "Cannot delete your own account"
}
```

---

## Force Reset Password

### POST /users/{id}/force-reset-password

Force a password reset for a user. Invalidates their current password and sends an email with a password reset link. Cannot reset your own password through this endpoint.

**Safety Check:** Returns error if attempting to reset your own password.

**Success Response:**

```json
{
    "status": "success",
    "message": "Password reset forced successfully. User will receive an email to set a new password.",
    "data": {
        "user": {
            "id": 5,
            "name": "Test User",
            "email": "user@example.com",
            "admin": false,
            "active": true,
            "must_change_password": true,
            "created_at": "2026-02-02T09:04:42+00:00",
            "updated_at": "2026-02-02T09:04:55+00:00"
        }
    }
}
```

**Self-Reset Error Response (HTTP 400):**

```json
{
    "status": "error",
    "code": "CANNOT_RESET_SELF",
    "message": "Cannot force reset your own password"
}
```

---

## User Object

**Field Types:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | User ID |
| `name` | `String` | Full name |
| `email` | `String` | Email address |
| `admin` | `Bool` | Whether user is an admin |
| `active` | `Bool` | Whether user account is active |
| `must_change_password` | `Bool` | Whether user must change password on next login |
| `created_at` | `String` | ISO 8601 timestamp |
| `updated_at` | `String` | ISO 8601 timestamp |

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `USER_NOT_FOUND` | 404 | User with specified ID does not exist |
| `CANNOT_DELETE_SELF` | 400 | Cannot delete your own account |
| `CANNOT_RESET_SELF` | 400 | Cannot force reset your own password |
| `USER_CREATE_FAILED` | 500 | Failed to create user |
| `USER_UPDATE_FAILED` | 500 | Failed to update user |
| `USER_DELETE_FAILED` | 500 | Failed to delete user |
| `PASSWORD_RESET_FAILED` | 500 | Failed to force password reset |

---

## Swift Type Definitions

```swift
// MARK: - User

struct User: Codable, Identifiable {
    let id: Int
    let name: String
    let email: String
    let admin: Bool
    let active: Bool
    let mustChangePassword: Bool
    let createdAt: String
    let updatedAt: String
    
    enum CodingKeys: String, CodingKey {
        case id
        case name
        case email
        case admin
        case active
        case mustChangePassword = "must_change_password"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }
}

// MARK: - List Users Response

struct UsersListResponse: Codable {
    let status: String
    let data: UsersListData
}

struct UsersListData: Codable {
    let users: [User]
    let pagination: Pagination
}

struct Pagination: Codable {
    let currentPage: Int
    let totalPages: Int
    let totalItems: Int
    let perPage: Int
    
    enum CodingKeys: String, CodingKey {
        case currentPage = "current_page"
        case totalPages = "total_pages"
        case totalItems = "total_items"
        case perPage = "per_page"
    }
}

// MARK: - Single User Response

struct UserResponse: Codable {
    let status: String
    let message: String?
    let data: UserData
}

struct UserData: Codable {
    let user: User
}

// MARK: - Create User Request

struct CreateUserRequest: Codable {
    let email: String
    let name: String
    var admin: Bool?
}

// MARK: - Update User Request

struct UpdateUserRequest: Codable {
    var email: String?
    var name: String?
    var admin: Bool?
    var mustChangePassword: Bool?
    
    enum CodingKeys: String, CodingKey {
        case email
        case name
        case admin
        case mustChangePassword = "must_change_password"
    }
}

// MARK: - Message Response (for delete)

struct MessageResponse: Codable {
    let status: String
    let message: String
}

// MARK: - Error Response

struct UserErrorResponse: Codable {
    let status: String
    let code: String
    let message: String
}

struct ValidationErrorResponse: Codable {
    let message: String
    let errors: [String: [String]]
}
```
