# Erugo Liveshares API

API specification for the App API liveshare endpoints. Liveshares provide collaborative file-sharing workspaces with role-based access control, tagging, and invite systems.

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

### Success Response with Message

```json
{
    "status": "success",
    "message": "Descriptive message",
    "data": { ... }
}
```

### Validation Error Response (HTTP 422)

```json
{
    "status": "error",
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "data": {
        "errors": {
            "field_name": [
                "Error message for this field."
            ]
        }
    }
}
```

### Not Found Response (HTTP 404)

```json
{
    "status": "error",
    "code": "LIVESHARE_NOT_FOUND",
    "message": "Liveshare not found"
}
```

### Access Denied Response (HTTP 403)

```json
{
    "status": "error",
    "code": "LIVESHARE_ACCESS_DENIED",
    "message": "You do not have access to this liveshare"
}
```

---

## Liveshare CRUD

### GET /liveshares

List all liveshares the authenticated user has access to (as owner or member).

**Response:**

```json
{
    "status": "success",
    "data": {
        "liveshares": [
            {
                "id": 1,
                "user_id": 1,
                "name": "Project Photos",
                "description": null,
                "long_id": "dWpQvA",
                "size": 54537601,
                "file_count": 20,
                "max_size_override": null,
                "max_files_per_user_override": null,
                "created_at": "2026-02-06T11:39:07.000000Z",
                "updated_at": "2026-02-06T17:13:41.000000Z",
                "my_role": "owner"
            }
        ]
    }
}
```

The `my_role` field indicates the user's role: `"owner"`, `"manager"`, `"collaborator"`, or `"viewer"`.

---

### POST /liveshares

Create a new liveshare.

**Request Body:**

```json
{
    "name": "My Liveshare",
    "description": "Optional description"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `name` | Required, string, max 255 characters |
| `description` | Optional, string, max 1000 characters |

**Success Response (HTTP 201):**

```json
{
    "status": "success",
    "message": "Liveshare created",
    "data": {
        "liveshare": {
            "id": 2,
            "user_id": 1,
            "name": "My Liveshare",
            "description": "Optional description",
            "long_id": "DMZrKp",
            "created_at": "2026-02-08T17:31:29.000000Z",
            "updated_at": "2026-02-08T17:31:29.000000Z",
            "my_role": "owner"
        }
    }
}
```

---

### GET /liveshares/{longId}

Get a single liveshare with full details including members, files, tags, and owner.

**Response:**

```json
{
    "status": "success",
    "data": {
        "liveshare": {
            "id": 1,
            "user_id": 1,
            "name": "Project Photos",
            "description": null,
            "long_id": "dWpQvA",
            "size": 54537601,
            "file_count": 20,
            "max_size_override": null,
            "max_files_per_user_override": null,
            "created_at": "2026-02-06T11:39:07.000000Z",
            "updated_at": "2026-02-06T17:13:41.000000Z",
            "my_role": "owner",
            "members": [ ... ],
            "files": [ ... ],
            "owner": { ... },
            "tags": [ ... ]
        }
    }
}
```

See the object reference sections below for the shape of `members`, `files`, `owner`, and `tags`.

---

### PUT /liveshares/{longId}

Update a liveshare. Requires owner or manager role.

**Request Body:**

```json
{
    "name": "Updated Name",
    "description": "Updated description"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `name` | Optional (if present, required), string, max 255 characters |
| `description` | Optional, string, max 1000 characters |

**Success Response:**

```json
{
    "status": "success",
    "message": "Liveshare updated",
    "data": {
        "liveshare": {
            "id": 1,
            "user_id": 1,
            "name": "Updated Name",
            "description": "Updated description",
            "long_id": "dWpQvA",
            "size": 54537601,
            "file_count": 20,
            "max_size_override": null,
            "max_files_per_user_override": null,
            "created_at": "2026-02-06T11:39:07.000000Z",
            "updated_at": "2026-02-08T17:31:29.000000Z"
        }
    }
}
```

---

### DELETE /liveshares/{longId}

Delete a liveshare and all associated files, members, tags, and invites. Requires owner role.

**Success Response:**

```json
{
    "status": "success",
    "message": "Liveshare deleted"
}
```

---

## Members

### GET /liveshares/{longId}/members

List all members and the owner of a liveshare.

**Response:**

```json
{
    "status": "success",
    "data": {
        "members": [
            {
                "id": 1,
                "liveshare_id": 1,
                "user_id": 9,
                "role": "manager",
                "created_at": "2026-02-06T12:29:40.000000Z",
                "updated_at": "2026-02-08T13:19:44.000000Z",
                "user": {
                    "id": 9,
                    "name": "Jane Smith",
                    "email": "jane@example.com"
                }
            }
        ],
        "owner": {
            "id": 1,
            "name": "Dean Ward",
            "email": "dean@example.com"
        }
    }
}
```

---

### POST /liveshares/{longId}/members

Add a member to a liveshare by email. Requires owner or manager role.

**Request Body:**

```json
{
    "email": "user@example.com",
    "role": "collaborator"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `email` | Required, valid email |
| `role` | Required, one of: `"manager"`, `"collaborator"`, `"viewer"` |

**Success Response (HTTP 201):**

```json
{
    "status": "success",
    "message": "Member added",
    "data": {
        "member": {
            "liveshare_id": 1,
            "user_id": 10,
            "role": "collaborator",
            "updated_at": "2026-02-08T17:31:29.000000Z",
            "created_at": "2026-02-08T17:31:29.000000Z",
            "id": 5,
            "user": { ... }
        }
    }
}
```

**Error Codes:**

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `LIVESHARE_MEMBER_USER_NOT_FOUND` | 404 | No user found with that email |
| `LIVESHARE_MEMBER_IS_OWNER` | 400 | Cannot add the owner as a member |
| `LIVESHARE_MEMBER_EXISTS` | 400 | User is already a member |

---

### PUT /liveshares/{longId}/members/{memberId}

Update a member's role. Requires owner or manager role.

**Request Body:**

```json
{
    "role": "viewer"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `role` | Required, one of: `"manager"`, `"collaborator"`, `"viewer"` |

**Success Response:**

```json
{
    "status": "success",
    "message": "Member updated",
    "data": {
        "member": {
            "id": 1,
            "liveshare_id": 1,
            "user_id": 9,
            "role": "viewer",
            "created_at": "2026-02-06T12:29:40.000000Z",
            "updated_at": "2026-02-08T17:31:29.000000Z"
        }
    }
}
```

---

### DELETE /liveshares/{longId}/members/{memberId}

Remove a member from a liveshare. Requires owner or manager role.

**Success Response:**

```json
{
    "status": "success",
    "message": "Member removed"
}
```

---

## Files

### GET /liveshares/{longId}/files

List files in a liveshare with optional search and tag filters.

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `search` | `String` | - | Filter files by name (partial match) |
| `tags` | `String` | - | Comma-separated tag IDs to filter by |
| `type` | `String` | - | Filter by auto-tag type (e.g. `"image"`, `"video"`) |

**Response:**

```json
{
    "status": "success",
    "data": {
        "files": [
            {
                "id": 1,
                "liveshare_id": 1,
                "uploaded_by": 1,
                "name": "IMG_3873.jpeg",
                "original_name": "IMG_3873.jpeg",
                "size": 5624531,
                "type": "image/jpeg",
                "full_path": "IMG_3873.jpeg",
                "created_at": "2026-02-06T11:39:45.000000Z",
                "updated_at": "2026-02-06T11:39:45.000000Z",
                "thumbnail_url": "/api/app/v1/liveshares/dWpQvA/files/1/thumb",
                "uploader": {
                    "id": 1,
                    "name": "Dean Ward"
                },
                "tags": [
                    {
                        "id": 3,
                        "name": "Spring 2026 Campaign",
                        "type": "custom",
                        "color": "#1abc9c"
                    }
                ]
            }
        ]
    }
}
```

The `thumbnail_url` field is a relative path using the app API prefix. It will be `null` for non-image files or if thumbnail generation is not supported.

---

### POST /liveshares/{longId}/files

Add files to a liveshare. Files must first be uploaded via TUS and referenced by their upload session IDs. Requires owner, manager, or collaborator role.

**Request Body:**

```json
{
    "uploadIds": [
        "upload-session-id-1",
        "upload-session-id-2"
    ]
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `uploadIds` | Required, array |
| `uploadIds.*` | Required, string (valid upload session IDs) |

**Success Response (HTTP 201):**

```json
{
    "status": "success",
    "message": "Files added",
    "data": {
        "files": [ ... ]
    }
}
```

**Error Codes:**

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `LIVESHARE_SIZE_EXCEEDED` | 400 | Adding these files would exceed the liveshare size limit |
| `LIVESHARE_FILE_LIMIT_EXCEEDED` | 400 | Adding these files would exceed the per-user file limit |

---

### DELETE /liveshares/{longId}/files/{fileId}

Remove a file from a liveshare. Requires owner, manager, or collaborator role.

**Success Response:**

```json
{
    "status": "success",
    "message": "File removed"
}
```

---

### GET /liveshares/{longId}/files/{fileId}/download

Download a single file. Returns the raw file data as an attachment.

**Response Headers:**

```
Content-Type: <file mime type>
Content-Disposition: attachment; filename=<sanitized filename>
```

---

### POST /liveshares/{longId}/files/download

Download multiple files as a ZIP archive. If no `fileIds` provided, downloads all files (optionally filtered by tags/search).

**Request Body:**

```json
{
    "fileIds": [1, 2, 3]
}
```

All fields are optional. If `fileIds` is omitted or empty, all files in the liveshare are included (subject to any `tags` or `search` query parameters).

**Query Parameters (when fileIds is omitted):**

| Parameter | Type | Description |
|-----------|------|-------------|
| `tags` | `String` | Comma-separated tag IDs to filter by |
| `search` | `String` | Filter files by name (partial match) |

**Response Headers:**

```
Content-Type: application/zip
Content-Disposition: attachment; filename=<liveshare-name>.zip
```

---

### GET /liveshares/{longId}/files/{fileId}/thumb

Get the thumbnail for a file. Returns a WebP image. Only available for image files.

**Response Headers:**

```
Content-Type: image/webp
Cache-Control: max-age=86400, public
```

---

## Tags

### GET /liveshares/{longId}/tags

List all tags for a liveshare, including a count of files using each tag.

**Response:**

```json
{
    "status": "success",
    "data": {
        "tags": [
            {
                "id": 3,
                "liveshare_id": 1,
                "name": "Spring 2026 Campaign",
                "type": "custom",
                "color": "#1abc9c",
                "created_by": 1,
                "created_at": "2026-02-08T14:59:30.000000Z",
                "updated_at": "2026-02-08T15:06:29.000000Z",
                "files_count": 6
            }
        ]
    }
}
```

Tags have a `type` of either `"custom"` (user-created) or `"auto"` (system-generated from file analysis).

---

### POST /liveshares/{longId}/tags

Create a new tag. Requires owner, manager, or collaborator role.

**Request Body:**

```json
{
    "name": "test-tag",
    "color": "#ff5722"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `name` | Required, string, max 50 characters |
| `color` | Optional, hex color string (e.g. `"#ff5722"`) |

**Success Response (HTTP 201):**

```json
{
    "status": "success",
    "message": "Tag created",
    "data": {
        "tag": {
            "id": 6,
            "liveshare_id": 1,
            "name": "test-tag",
            "type": "custom",
            "color": "#ff5722",
            "created_by": 1,
            "created_at": "2026-02-08T17:31:29.000000Z",
            "updated_at": "2026-02-08T17:31:29.000000Z",
            "files_count": 0
        }
    }
}
```

**Error Codes:**

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `LIVESHARE_TAG_DUPLICATE` | 409 | A tag with this name already exists in the liveshare |

---

### PUT /liveshares/{longId}/tags/{tagId}

Update a tag. Requires owner, manager, or collaborator role.

**Request Body:**

```json
{
    "name": "updated-name",
    "color": "#2196f3"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `name` | Optional (if present, required), string, max 50 characters |
| `color` | Optional, hex color string (e.g. `"#2196f3"`) |

**Success Response:**

```json
{
    "status": "success",
    "message": "Tag updated",
    "data": {
        "tag": { ... }
    }
}
```

---

### DELETE /liveshares/{longId}/tags/{tagId}

Delete a tag. Requires owner, manager, or collaborator role.

**Success Response:**

```json
{
    "status": "success",
    "message": "Tag deleted"
}
```

---

## File Tags

### POST /liveshares/{longId}/files/{fileId}/tags

Add tags to a single file. Requires owner, manager, or collaborator role.

**Request Body:**

```json
{
    "tagIds": [1, 3, 5]
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `tagIds` | Required, array |
| `tagIds.*` | Required, integer |

**Success Response:**

```json
{
    "status": "success",
    "message": "Tags added",
    "data": {
        "tags": [
            {
                "id": 1,
                "name": "person",
                "type": "custom",
                "color": "#e91e63"
            }
        ]
    }
}
```

---

### DELETE /liveshares/{longId}/files/{fileId}/tags/{tagId}

Remove a tag from a file. Requires owner, manager, or collaborator role.

**Success Response:**

```json
{
    "status": "success",
    "message": "Tag removed"
}
```

---

### POST /liveshares/{longId}/files/bulk-tag

Add tags to multiple files at once. Requires owner, manager, or collaborator role.

**Request Body:**

```json
{
    "fileIds": [1, 2, 3],
    "tagIds": [5, 6]
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `fileIds` | Required, array |
| `fileIds.*` | Required, integer |
| `tagIds` | Required, array |
| `tagIds.*` | Required, integer |

**Success Response:**

```json
{
    "status": "success",
    "message": "Tags added to files"
}
```

---

### POST /liveshares/{longId}/files/bulk-untag

Remove tags from multiple files at once. Requires owner, manager, or collaborator role.

**Request Body:**

```json
{
    "fileIds": [1, 2, 3],
    "tagIds": [5, 6]
}
```

**Validation Rules:**

Same as `bulk-tag` above.

**Success Response:**

```json
{
    "status": "success",
    "message": "Tags removed from files"
}
```

---

## Invites

### POST /liveshares/{longId}/invites/email

Send an email invite to join the liveshare. Requires owner or manager role.

**Request Body:**

```json
{
    "email": "user@example.com",
    "role": "collaborator"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `email` | Required, valid email |
| `role` | Required, one of: `"manager"`, `"collaborator"`, `"viewer"` |

**Success Response (HTTP 201):**

```json
{
    "status": "success",
    "message": "Email invite sent",
    "data": {
        "invite": {
            "id": 5,
            "liveshare_id": 1,
            "created_by": 1,
            "type": "email",
            "email": "user@example.com",
            "token": "A4Gbta0KGfQCO6pMPa5KA6...",
            "role": "collaborator",
            "max_uses": 1,
            "expires_at": null,
            "created_at": "2026-02-08T17:32:09.000000Z",
            "updated_at": "2026-02-08T17:32:09.000000Z",
            "creator": { ... }
        }
    }
}
```

---

### POST /liveshares/{longId}/invites/link

Create a shareable link invite. Requires owner or manager role.

**Request Body:**

```json
{
    "role": "viewer",
    "max_uses": 5,
    "expires_at": "2026-03-01T00:00:00Z"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `role` | Required, one of: `"manager"`, `"collaborator"`, `"viewer"` |
| `max_uses` | Optional, integer, minimum 1 |
| `expires_at` | Optional, date string, must be in the future |

**Success Response (HTTP 201):**

```json
{
    "status": "success",
    "message": "Link invite created",
    "data": {
        "invite": {
            "id": 4,
            "liveshare_id": 1,
            "created_by": 1,
            "type": "link",
            "email": null,
            "token": "CMo8DGk6TcEYUQ1delcKaX...",
            "role": "viewer",
            "max_uses": 5,
            "use_count": 0,
            "expires_at": "2026-03-01T00:00:00.000000Z",
            "created_at": "2026-02-08T17:31:30.000000Z",
            "updated_at": "2026-02-08T17:31:30.000000Z",
            "creator": { ... }
        },
        "invite_url": "https://example.com/liveshares/invite/CMo8DGk6TcEYUQ1delcKaX..."
    }
}
```

---

### GET /liveshares/{longId}/invites

List all invites for a liveshare. Requires owner or manager role.

**Response:**

```json
{
    "status": "success",
    "data": {
        "invites": [
            {
                "id": 1,
                "liveshare_id": 1,
                "created_by": 1,
                "type": "link",
                "email": null,
                "token": "CrGwcwkcbPojCOiEbc802...",
                "role": "collaborator",
                "max_uses": null,
                "use_count": 0,
                "expires_at": null,
                "created_at": "2026-02-08T12:38:53.000000Z",
                "updated_at": "2026-02-08T12:38:53.000000Z",
                "invite_url": "https://example.com/liveshares/invite/CrGwcwkcbPojCOiEbc802...",
                "can_be_used": true,
                "creator": {
                    "id": 1,
                    "name": "Dean Ward",
                    "email": "dean@example.com"
                }
            }
        ]
    }
}
```

---

### DELETE /liveshares/{longId}/invites/{inviteId}

Revoke an invite. Requires owner or manager role.

**Success Response:**

```json
{
    "status": "success",
    "message": "Invite revoked"
}
```

---

### POST /liveshares/invite/{token}/accept

Accept an invite as an authenticated user. Adds the user as a member of the liveshare.

**No request body required.**

**Success Response:**

```json
{
    "status": "success",
    "message": "You have joined the liveshare",
    "data": {
        "liveshare_long_id": "dWpQvA"
    }
}
```

**Already a Member Response:**

```json
{
    "status": "success",
    "message": "You are already a member of this liveshare",
    "data": {
        "liveshare_long_id": "dWpQvA",
        "already_member": true
    }
}
```

**Error Codes:**

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `LIVESHARE_INVITE_NOT_FOUND` | 404 | No invite found with this token |
| `LIVESHARE_INVITE_EXPIRED` | 410 | The invite has expired |
| `LIVESHARE_INVITE_EXHAUSTED` | 410 | The invite has reached its maximum use count |
| `LIVESHARE_INVITE_EMAIL_MISMATCH` | 403 | Email invite was sent to a different address |
| `LIVESHARE_MEMBER_EXISTS` | 400 | User is the owner of this liveshare |

---

## Public Endpoints (No Authentication Required)

### GET /liveshares/invite/{token}

Get information about an invite before accepting it.

**Response:**

```json
{
    "status": "success",
    "data": {
        "liveshare_name": "Project Photos",
        "liveshare_long_id": "dWpQvA",
        "inviter_name": "Dean",
        "role": "collaborator",
        "type": "link"
    }
}
```

---

### POST /liveshares/invite/{token}/register

Register a new user account via an invite token. Returns JWT tokens for immediate authentication, plus the `liveshare_long_id` for client-side navigation.

**Request Body:**

```json
{
    "name": "New User",
    "email": "newuser@example.com",
    "password": "securepassword",
    "password_confirmation": "securepassword"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `name` | Required, string, max 255 characters |
| `email` | Required, valid email, unique |
| `password` | Required, confirmed, minimum 8 characters |
| `password_confirmation` | Required, must match `password` |

**Success Response:**

```json
{
    "status": "success",
    "message": "Registration successful",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
        "token_type": "Bearer",
        "access_token_expires_in": 2592000,
        "refresh_token_expires_in": 2592000,
        "user": {
            "id": 12,
            "name": "New User",
            "email": "newuser@example.com",
            "admin": false,
            "is_guest": false,
            "must_change_password": false
        },
        "liveshare_long_id": "dWpQvA"
    }
}
```

**Error Codes:**

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `LIVESHARE_INVITE_NOT_FOUND` | 404 | No invite found with this token |
| `LIVESHARE_INVITE_EXPIRED` | 410 | The invite has expired |
| `LIVESHARE_INVITE_EXHAUSTED` | 410 | The invite has reached its maximum use count |
| `LIVESHARE_INVITE_EMAIL_MISMATCH` | 403 | Email invite was sent to a different address |
| `AUTH_TOKEN_GENERATION_FAILED` | 500 | Failed to generate access token |

---

### GET /liveshares/{longId}/avatar

Get the liveshare avatar image. This endpoint is public but requires either JWT authentication or an `invite_token` query parameter.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `invite_token` | `String` | A valid invite token (used for unauthenticated access on invite acceptance pages) |

**Example (with invite token):**
```
GET /api/app/v1/liveshares/dWpQvA/avatar?invite_token=CrGwcwkcbPojCOiEbc802...
```

**Response Headers:**

```
Content-Type: image/webp
Cache-Control: max-age=86400, public
```

Returns a 403 if neither valid JWT nor valid invite token is provided.

---

## Admin Endpoints

These endpoints require admin authentication.

### GET /liveshares/admin/all

List all liveshares in the system with their owners.

**Response:**

```json
{
    "status": "success",
    "data": {
        "liveshares": [
            {
                "id": 1,
                "user_id": 1,
                "name": "Project Photos",
                "description": null,
                "long_id": "dWpQvA",
                "size": 54537601,
                "file_count": 20,
                "max_size_override": null,
                "max_files_per_user_override": null,
                "created_at": "2026-02-06T11:39:07.000000Z",
                "updated_at": "2026-02-08T17:32:09.000000Z",
                "owner": {
                    "id": 1,
                    "name": "Dean Ward",
                    "email": "dean@example.com"
                }
            }
        ]
    }
}
```

---

### PUT /liveshares/admin/{id}/limits

Set per-liveshare limit overrides. Uses the numeric `id`, not `longId`.

**Request Body:**

```json
{
    "max_size_override": 10737418240,
    "max_files_per_user_override": 200
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `max_size_override` | Optional, integer (bytes), minimum 0, nullable (set to `null` to clear) |
| `max_files_per_user_override` | Optional, integer, minimum 0, nullable (set to `null` to clear) |

**Success Response:**

```json
{
    "status": "success",
    "message": "Limits updated",
    "data": {
        "liveshare": {
            "id": 1,
            "user_id": 1,
            "name": "Project Photos",
            "description": null,
            "long_id": "dWpQvA",
            "size": 54537601,
            "file_count": 20,
            "max_size_override": 10737418240,
            "max_files_per_user_override": 200,
            "created_at": "2026-02-06T11:39:07.000000Z",
            "updated_at": "2026-02-08T17:32:09.000000Z"
        }
    }
}
```

---

## Config Endpoint (Liveshare Fields)

The existing `GET /config` endpoint includes liveshare-related fields.

**Liveshare fields in the config response:**

```json
{
    "status": "success",
    "data": {
        "features": {
            "liveshares_enabled": true
        },
        "limits": {
            "liveshare_max_size_bytes": 5368709120,
            "liveshare_max_size_formatted": "5 GB",
            "liveshare_max_files_per_user": 100
        }
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `features.liveshares_enabled` | `Bool` | Whether the liveshares feature is enabled |
| `limits.liveshare_max_size_bytes` | `Int` | Default max liveshare size in bytes |
| `limits.liveshare_max_size_formatted` | `String` | Human-readable max size |
| `limits.liveshare_max_files_per_user` | `Int` | Default max files per user per liveshare |

---

## Role-Based Access Control

Liveshares use a role hierarchy. The following table shows which operations are available for each role:

| Operation | Owner | Manager | Collaborator | Viewer |
|-----------|-------|---------|--------------|--------|
| View liveshare / files | Yes | Yes | Yes | Yes |
| Download files | Yes | Yes | Yes | Yes |
| Add files | Yes | Yes | Yes | No |
| Remove files | Yes | Yes | Yes | No |
| Create/edit/delete tags | Yes | Yes | Yes | No |
| Tag/untag files | Yes | Yes | Yes | No |
| Add/remove members | Yes | Yes | No | No |
| Update member roles | Yes | Yes | No | No |
| Create/revoke invites | Yes | Yes | No | No |
| Update liveshare | Yes | Yes | No | No |
| Delete liveshare | Yes | No | No | No |

System admins bypass all access checks.

---

## Object Reference

### Liveshare Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | Internal ID |
| `user_id` | `Int` | Owner's user ID |
| `name` | `String` | Liveshare name |
| `description` | `String?` | Optional description |
| `long_id` | `String` | Short unique identifier used in URLs |
| `size` | `Int` | Total size of all files in bytes |
| `file_count` | `Int` | Number of files |
| `max_size_override` | `Int?` | Admin-set size limit override in bytes |
| `max_files_per_user_override` | `Int?` | Admin-set file count limit override |
| `created_at` | `String` | ISO 8601 timestamp |
| `updated_at` | `String` | ISO 8601 timestamp |
| `my_role` | `String` | Current user's role (only on list/show) |

### Member Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | Member record ID |
| `liveshare_id` | `Int` | Liveshare ID |
| `user_id` | `Int` | User ID |
| `role` | `String` | One of: `"manager"`, `"collaborator"`, `"viewer"` |
| `created_at` | `String` | ISO 8601 timestamp |
| `updated_at` | `String` | ISO 8601 timestamp |
| `user` | `Object` | Nested user object |

### File Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | File ID |
| `liveshare_id` | `Int` | Liveshare ID |
| `uploaded_by` | `Int` | Uploader's user ID |
| `name` | `String` | Stored filename |
| `original_name` | `String` | Original upload filename |
| `size` | `Int` | File size in bytes |
| `type` | `String` | MIME type |
| `full_path` | `String` | Relative path within the liveshare |
| `created_at` | `String` | ISO 8601 timestamp |
| `updated_at` | `String` | ISO 8601 timestamp |
| `thumbnail_url` | `String?` | Relative URL to the thumbnail (null for non-image files) |
| `uploader` | `Object` | Nested user object |
| `tags` | `[Object]` | Array of tag objects attached to this file |

### Tag Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | Tag ID |
| `liveshare_id` | `Int` | Liveshare ID |
| `name` | `String` | Tag name |
| `type` | `String` | `"custom"` or `"auto"` |
| `color` | `String?` | Hex color string (e.g. `"#1abc9c"`) |
| `created_by` | `Int` | Creator's user ID |
| `created_at` | `String` | ISO 8601 timestamp |
| `updated_at` | `String` | ISO 8601 timestamp |
| `files_count` | `Int` | Number of files using this tag (on list endpoints) |

### Invite Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | Invite ID |
| `liveshare_id` | `Int` | Liveshare ID |
| `created_by` | `Int` | Creator's user ID |
| `type` | `String` | `"email"` or `"link"` |
| `email` | `String?` | Recipient email (for email invites) |
| `token` | `String` | Invite token |
| `role` | `String` | Role to assign: `"manager"`, `"collaborator"`, or `"viewer"` |
| `max_uses` | `Int?` | Maximum number of uses (null = unlimited) |
| `use_count` | `Int` | Number of times the invite has been used |
| `expires_at` | `String?` | ISO 8601 expiry timestamp (null = never) |
| `created_at` | `String` | ISO 8601 timestamp |
| `updated_at` | `String` | ISO 8601 timestamp |
| `invite_url` | `String` | Full URL to accept the invite (on list endpoint) |
| `can_be_used` | `Bool` | Whether the invite can still be used (on list endpoint) |
| `creator` | `Object` | Nested user object |

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `LIVESHARE_NOT_FOUND` | 404 | Liveshare with specified ID does not exist |
| `LIVESHARE_ACCESS_DENIED` | 403 | User does not have access to this liveshare |
| `LIVESHARE_MANAGE_DENIED` | 403 | User does not have manage permissions |
| `LIVESHARE_NOT_OWNER` | 403 | Only the owner can perform this action |
| `LIVESHARE_ADD_FILES_DENIED` | 403 | User cannot add files (viewer role) |
| `LIVESHARE_REMOVE_FILES_DENIED` | 403 | User cannot remove files (viewer role) |
| `LIVESHARE_SIZE_EXCEEDED` | 400 | Adding files would exceed size limit |
| `LIVESHARE_FILE_LIMIT_EXCEEDED` | 400 | Adding files would exceed per-user file limit |
| `LIVESHARE_FILE_NOT_FOUND` | 404 | File not found in this liveshare |
| `LIVESHARE_TAG_NOT_FOUND` | 404 | Tag not found in this liveshare |
| `LIVESHARE_TAG_DUPLICATE` | 409 | Tag with this name already exists |
| `LIVESHARE_MEMBER_USER_NOT_FOUND` | 404 | No user found with that email |
| `LIVESHARE_MEMBER_IS_OWNER` | 400 | Cannot add the owner as a member |
| `LIVESHARE_MEMBER_EXISTS` | 400 | User is already a member |
| `LIVESHARE_MEMBER_NOT_FOUND` | 404 | Member not found |
| `LIVESHARE_INVITE_NOT_FOUND` | 404 | Invite not found |
| `LIVESHARE_INVITE_EXPIRED` | 410 | Invite has expired |
| `LIVESHARE_INVITE_EXHAUSTED` | 410 | Invite max uses reached |
| `LIVESHARE_INVITE_EMAIL_MISMATCH` | 403 | Email invite was sent to a different address |
| `LIVESHARE_AVATAR_NOT_FOUND` | 404 | Liveshare has no avatar |
| `LIVESHARE_AVATAR_ACCESS_DENIED` | 403 | No valid auth or invite token for avatar access |
| `VALIDATION_ERROR` | 422 | Request body validation failed |
| `AUTH_UNAUTHORIZED` | 401 | Missing or invalid JWT token |
| `AUTH_TOKEN_GENERATION_FAILED` | 500 | Failed to generate access token |

---

## Swift Type Definitions

```swift
// MARK: - Liveshare

struct Liveshare: Codable, Identifiable {
    let id: Int
    let userId: Int
    let name: String
    let description: String?
    let longId: String
    let size: Int
    let fileCount: Int
    let maxSizeOverride: Int?
    let maxFilesPerUserOverride: Int?
    let createdAt: String
    let updatedAt: String
    let myRole: String?

    // Only on show response
    let members: [LiveshareMember]?
    let files: [LiveshareFile]?
    let owner: LiveshareUser?
    let tags: [LiveshareTag]?

    enum CodingKeys: String, CodingKey {
        case id
        case userId = "user_id"
        case name
        case description
        case longId = "long_id"
        case size
        case fileCount = "file_count"
        case maxSizeOverride = "max_size_override"
        case maxFilesPerUserOverride = "max_files_per_user_override"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case myRole = "my_role"
        case members
        case files
        case owner
        case tags
    }
}

// MARK: - LiveshareUser

struct LiveshareUser: Codable, Identifiable {
    let id: Int
    let name: String
    let email: String

    enum CodingKeys: String, CodingKey {
        case id
        case name
        case email
    }
}

// MARK: - LiveshareMember

struct LiveshareMember: Codable, Identifiable {
    let id: Int
    let liveshareId: Int
    let userId: Int
    let role: LiveshareRole
    let createdAt: String
    let updatedAt: String
    let user: LiveshareUser?

    enum CodingKeys: String, CodingKey {
        case id
        case liveshareId = "liveshare_id"
        case userId = "user_id"
        case role
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case user
    }
}

enum LiveshareRole: String, Codable {
    case owner = "owner"
    case manager = "manager"
    case collaborator = "collaborator"
    case viewer = "viewer"
}

// MARK: - LiveshareFile

struct LiveshareFile: Codable, Identifiable {
    let id: Int
    let liveshareId: Int
    let uploadedBy: Int
    let name: String
    let originalName: String
    let size: Int
    let type: String
    let fullPath: String
    let createdAt: String
    let updatedAt: String
    let thumbnailUrl: String?
    let uploader: LiveshareUser?
    let tags: [LiveshareFileTag]?

    enum CodingKeys: String, CodingKey {
        case id
        case liveshareId = "liveshare_id"
        case uploadedBy = "uploaded_by"
        case name
        case originalName = "original_name"
        case size
        case type
        case fullPath = "full_path"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case thumbnailUrl = "thumbnail_url"
        case uploader
        case tags
    }
}

// MARK: - LiveshareFileTag (lightweight tag on file objects)

struct LiveshareFileTag: Codable, Identifiable {
    let id: Int
    let name: String
    let type: String
    let color: String?

    enum CodingKeys: String, CodingKey {
        case id
        case name
        case type
        case color
    }
}

// MARK: - LiveshareTag (full tag from tag list)

struct LiveshareTag: Codable, Identifiable {
    let id: Int
    let liveshareId: Int
    let name: String
    let type: String
    let color: String?
    let createdBy: Int
    let createdAt: String
    let updatedAt: String
    let filesCount: Int?

    enum CodingKeys: String, CodingKey {
        case id
        case liveshareId = "liveshare_id"
        case name
        case type
        case color
        case createdBy = "created_by"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case filesCount = "files_count"
    }
}

// MARK: - LiveshareInvite

struct LiveshareInvite: Codable, Identifiable {
    let id: Int
    let liveshareId: Int
    let createdBy: Int
    let type: String
    let email: String?
    let token: String
    let role: LiveshareRole
    let maxUses: Int?
    let useCount: Int
    let expiresAt: String?
    let createdAt: String
    let updatedAt: String
    let inviteUrl: String?
    let canBeUsed: Bool?
    let creator: LiveshareUser?

    enum CodingKeys: String, CodingKey {
        case id
        case liveshareId = "liveshare_id"
        case createdBy = "created_by"
        case type
        case email
        case token
        case role
        case maxUses = "max_uses"
        case useCount = "use_count"
        case expiresAt = "expires_at"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case inviteUrl = "invite_url"
        case canBeUsed = "can_be_used"
        case creator
    }
}

// MARK: - Invite Info (public endpoint)

struct LiveshareInviteInfo: Codable {
    let liveshareName: String
    let liveshareLongId: String
    let inviterName: String
    let role: LiveshareRole
    let type: String

    enum CodingKeys: String, CodingKey {
        case liveshareName = "liveshare_name"
        case liveshareLongId = "liveshare_long_id"
        case inviterName = "inviter_name"
        case role
        case type
    }
}

// MARK: - Response Wrappers

struct LivesharesListResponse: Codable {
    let status: String
    let data: LivesharesListData
}

struct LivesharesListData: Codable {
    let liveshares: [Liveshare]
}

struct LiveshareResponse: Codable {
    let status: String
    let message: String?
    let data: LiveshareData
}

struct LiveshareData: Codable {
    let liveshare: Liveshare
}

struct LiveshareMembersResponse: Codable {
    let status: String
    let data: LiveshareMembersData
}

struct LiveshareMembersData: Codable {
    let members: [LiveshareMember]
    let owner: LiveshareUser
}

struct LiveshareFilesResponse: Codable {
    let status: String
    let data: LiveshareFilesData
}

struct LiveshareFilesData: Codable {
    let files: [LiveshareFile]
}

struct LiveshareTagsResponse: Codable {
    let status: String
    let data: LiveshareTagsData
}

struct LiveshareTagsData: Codable {
    let tags: [LiveshareTag]
}

struct LiveshareInvitesResponse: Codable {
    let status: String
    let data: LiveshareInvitesData
}

struct LiveshareInvitesData: Codable {
    let invites: [LiveshareInvite]
}

struct LiveshareAcceptInviteResponse: Codable {
    let status: String
    let message: String
    let data: LiveshareAcceptInviteData
}

struct LiveshareAcceptInviteData: Codable {
    let liveshareLongId: String
    let alreadyMember: Bool?

    enum CodingKeys: String, CodingKey {
        case liveshareLongId = "liveshare_long_id"
        case alreadyMember = "already_member"
    }
}

struct LiveshareInviteInfoResponse: Codable {
    let status: String
    let data: LiveshareInviteInfo
}

// MARK: - Request Bodies

struct CreateLiveshareRequest: Codable {
    let name: String
    var description: String?
}

struct UpdateLiveshareRequest: Codable {
    var name: String?
    var description: String?
}

struct AddMemberRequest: Codable {
    let email: String
    let role: LiveshareRole
}

struct UpdateMemberRequest: Codable {
    let role: LiveshareRole
}

struct AddFilesRequest: Codable {
    let uploadIds: [String]
}

struct DownloadFilesRequest: Codable {
    var fileIds: [Int]?
}

struct CreateTagRequest: Codable {
    let name: String
    var color: String?
}

struct UpdateTagRequest: Codable {
    var name: String?
    var color: String?
}

struct AddFileTagsRequest: Codable {
    let tagIds: [Int]
}

struct BulkFileTagsRequest: Codable {
    let fileIds: [Int]
    let tagIds: [Int]
}

struct CreateEmailInviteRequest: Codable {
    let email: String
    let role: LiveshareRole
}

struct CreateLinkInviteRequest: Codable {
    let role: LiveshareRole
    var maxUses: Int?
    var expiresAt: String?

    enum CodingKeys: String, CodingKey {
        case role
        case maxUses = "max_uses"
        case expiresAt = "expires_at"
    }
}

struct AdminSetLimitsRequest: Codable {
    var maxSizeOverride: Int?
    var maxFilesPerUserOverride: Int?

    enum CodingKeys: String, CodingKey {
        case maxSizeOverride = "max_size_override"
        case maxFilesPerUserOverride = "max_files_per_user_override"
    }
}

struct RegisterViaInviteRequest: Codable {
    let name: String
    let email: String
    let password: String
    let passwordConfirmation: String

    enum CodingKeys: String, CodingKey {
        case name
        case email
        case password
        case passwordConfirmation = "password_confirmation"
    }
}

// MARK: - Error Response

struct LiveshareErrorResponse: Codable {
    let status: String
    let code: String
    let message: String
}

struct LiveshareValidationErrorResponse: Codable {
    let status: String
    let code: String
    let message: String
    let data: LiveshareValidationErrorData?
}

struct LiveshareValidationErrorData: Codable {
    let errors: [String: [String]]
}
```
