# Erugo Settings API

API specification for the App API settings endpoints. All settings endpoints require admin authentication.

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
    "message": "The selected max share size unit is invalid.",
    "errors": {
        "field_name": [
            "Error message for this field."
        ]
    }
}
```

### Authentication Error Response (HTTP 401)

```json
{
    "message": "Unauthenticated."
}
```

---

## Shares Settings

Manage share-related settings including size limits, expiry times, and upload modes.

### GET /settings/shares

Returns all shares settings.

**Response:**

```json
{
    "status": "success",
    "data": {
        "max_share_size": 500,
        "max_share_size_unit": "MB",
        "default_expiry_time": 7,
        "max_expiry_time": 10,
        "expiry_warning_days": 3,
        "deletion_warning_days": 7,
        "clean_files_after_days": 30,
        "allow_reverse_shares": true,
        "share_url_mode": "haiku",
        "share_url_pattern": "******",
        "default_upload_mode": "chunked",
        "allow_direct_uploads": true,
        "allow_chunked_uploads": true
    }
}
```

**Field Types:**

| Field | Type | Description |
|-------|------|-------------|
| `max_share_size` | `Int` | Maximum share size value |
| `max_share_size_unit` | `String` | Size unit: `"MB"`, `"GB"`, or `"TB"` |
| `default_expiry_time` | `Int` | Default expiry in days |
| `max_expiry_time` | `Int` | Maximum expiry in days |
| `expiry_warning_days` | `Int` | Days before expiry to warn user |
| `deletion_warning_days` | `Int` | Days before deletion to warn user |
| `clean_files_after_days` | `Int` | Days after expiry to delete files |
| `allow_reverse_shares` | `Bool` | Whether reverse shares are enabled |
| `share_url_mode` | `String` | URL generation mode: `"haiku"` or `"pattern"` |
| `share_url_pattern` | `String` | Pattern for URL generation (when mode is `"pattern"`) |
| `default_upload_mode` | `String` | Default upload mode: `"chunked"` or `"direct"` |
| `allow_direct_uploads` | `Bool` | Whether direct uploads are allowed |
| `allow_chunked_uploads` | `Bool` | Whether chunked uploads are allowed |

---

### PUT /settings/shares

Update shares settings. Supports partial updates - only include fields you want to change.

**Request Body:**

All fields are optional. Include only the fields you want to update.

```json
{
    "max_share_size": 1000,
    "max_share_size_unit": "GB"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `max_share_size` | Integer, minimum 1 |
| `max_share_size_unit` | One of: `"MB"`, `"GB"`, `"TB"` |
| `default_expiry_time` | Integer, minimum 1 |
| `max_expiry_time` | Integer, minimum 1, must be >= `default_expiry_time` |
| `expiry_warning_days` | Integer, minimum 0 |
| `deletion_warning_days` | Integer, minimum 0 |
| `clean_files_after_days` | Integer, minimum 1 |
| `allow_reverse_shares` | Boolean |
| `share_url_mode` | One of: `"haiku"`, `"pattern"` |
| `share_url_pattern` | String, max 255 characters |
| `default_upload_mode` | One of: `"chunked"`, `"direct"` |
| `allow_direct_uploads` | Boolean |
| `allow_chunked_uploads` | Boolean |

**Cross-field Validation:**
- `max_expiry_time` must be greater than or equal to `default_expiry_time`

**Success Response:**

Returns the complete updated settings (same format as GET).

```json
{
    "status": "success",
    "data": {
        "max_share_size": 1000,
        "max_share_size_unit": "GB",
        "default_expiry_time": 7,
        ...
    }
}
```

**Validation Error Example:**

```json
{
    "message": "Max expiry time must be greater than or equal to default expiry time.",
    "errors": {
        "max_expiry_time": [
            "Max expiry time must be greater than or equal to default expiry time."
        ]
    }
}
```

---

## General Settings

Manage general application settings including URL, language, and UI options.

### GET /settings/general

Returns all general settings.

**Response:**

```json
{
    "status": "success",
    "data": {
        "application_url": "https://example.com",
        "default_language": "en",
        "show_language_selector": true
    }
}
```

**Field Types:**

| Field | Type | Description |
|-------|------|-------------|
| `application_url` | `String` | Base URL for the application |
| `default_language` | `String` | Default language code: `"en"`, `"de"`, `"fr"`, `"it"`, `"nl"`, `"pt"`, or `"pt-BR"` |
| `show_language_selector` | `Bool` | Whether to show language selector in UI |

---

### PUT /settings/general

Update general settings. Supports partial updates - only include fields you want to change.

**Request Body:**

All fields are optional. Include only the fields you want to update.

```json
{
    "application_url": "https://example.com",
    "default_language": "de"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `application_url` | Valid URL, max 255 characters. Trailing slashes are automatically stripped. |
| `default_language` | One of: `"en"`, `"de"`, `"fr"`, `"it"`, `"nl"`, `"pt"`, `"pt-BR"` |
| `show_language_selector` | Boolean |

**Success Response:**

Returns the complete updated settings (same format as GET).

```json
{
    "status": "success",
    "data": {
        "application_url": "https://example.com",
        "default_language": "de",
        "show_language_selector": true
    }
}
```

**Validation Error Example:**

```json
{
    "message": "The application url field must be a valid URL.",
    "errors": {
        "application_url": [
            "The application url field must be a valid URL."
        ]
    }
}
```

---

## Branding Settings

Manage UI branding settings including application name, colors, logo, and display options.

### GET /settings/branding

Returns all branding settings.

**Response:**

```json
{
    "status": "success",
    "data": {
        "application_name": "Erugo File Sharing",
        "login_message": "Login to your account to get started.",
        "logo_width": 100,
        "css_primary_color": "#589db6",
        "css_secondary_color": "#01021c",
        "css_accent_color": "#63a8bc",
        "css_accent_color_light": "#d0e1d5",
        "use_my_backgrounds": false,
        "background_slideshow_speed": 3,
        "show_powered_by": true
    }
}
```

**Field Types:**

| Field | Type | Description |
|-------|------|-------------|
| `application_name` | `String` | Application display name |
| `login_message` | `String?` | Message shown on login page (nullable) |
| `logo_width` | `Int` | Logo width in pixels (50-500) |
| `css_primary_color` | `String` | Primary color as hex (e.g., `"#589db6"`) |
| `css_secondary_color` | `String` | Secondary color as hex |
| `css_accent_color` | `String` | Accent color as hex |
| `css_accent_color_light` | `String` | Light accent color as hex |
| `use_my_backgrounds` | `Bool` | Whether to use custom backgrounds |
| `background_slideshow_speed` | `Int` | Slideshow speed in seconds (0 = disabled) |
| `show_powered_by` | `Bool` | Whether to show "Powered by Erugo" |

---

### PUT /settings/branding

Update branding settings. Supports partial updates - only include fields you want to change.

**Request Body:**

All fields are optional. Include only the fields you want to update.

```json
{
    "application_name": "My App",
    "css_primary_color": "#FF5733"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `application_name` | String, max 255 characters |
| `login_message` | String, max 500 characters, nullable |
| `logo_width` | Integer, 50-500 |
| `css_primary_color` | Valid hex color (e.g., `#FF5733`) |
| `css_secondary_color` | Valid hex color |
| `css_accent_color` | Valid hex color |
| `css_accent_color_light` | Valid hex color |
| `use_my_backgrounds` | Boolean |
| `background_slideshow_speed` | Integer, minimum 0 (0 = disabled) |
| `show_powered_by` | Boolean |

**Success Response:**

Returns the complete updated settings (same format as GET).

```json
{
    "status": "success",
    "data": {
        "application_name": "My App",
        "login_message": "Login to your account to get started.",
        "logo_width": 100,
        "css_primary_color": "#FF5733",
        ...
    }
}
```

**Validation Error Example:**

```json
{
    "message": "The primary color must be a valid hex color (e.g., #FF5733).",
    "errors": {
        "css_primary_color": [
            "The primary color must be a valid hex color (e.g., #FF5733)."
        ]
    }
}
```

---

## Branding Assets

Manage logo, favicon, and background images. These are file-based assets separate from the branding settings.

### Logo

#### GET /branding/logo

Returns the current logo image file (PNG format).

**Response:** Binary image data with `Content-Type: image/png`

---

#### POST /branding/logo (Admin Only)

Upload a new logo image.

**Request:** `multipart/form-data`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `logo` | File | Yes | PNG or SVG image, max 2MB |

**Success Response:**

```json
{
    "status": "success",
    "message": "Logo updated successfully"
}
```

**Validation Error Response (HTTP 422):**

```json
{
    "status": "error",
    "error_code": "file_too_large",
    "message": "The logo field must not be greater than 2048 kilobytes.",
    "errors": {
        "logo": ["The logo field must not be greater than 2048 kilobytes."]
    }
}
```

Error codes: `file_too_large`, `invalid_file_type`, `invalid_image`, `file_required`

---

#### DELETE /branding/logo (Admin Only)

Reset logo to default.

**Success Response:**

```json
{
    "status": "success",
    "message": "Logo reset to default successfully"
}
```

---

### Favicon

#### GET /branding/favicon

Returns the current favicon image file.

**Response:** Binary image data with `Content-Type: image/png` or `image/svg+xml`

---

#### GET /branding/favicon/status

Check if a custom favicon is set.

**Response:**

```json
{
    "status": "success",
    "data": {
        "has_custom_favicon": false,
        "filename": null
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `has_custom_favicon` | `Bool` | Whether a custom favicon is set |
| `filename` | `String?` | Filename if custom favicon exists: `"favicon.png"` or `"favicon.svg"` |

---

#### POST /branding/favicon (Admin Only)

Upload a new favicon.

**Request:** `multipart/form-data`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `favicon` | File | Yes | PNG or SVG image, max 1MB |

**Success Response:**

```json
{
    "status": "success",
    "message": "Favicon updated successfully",
    "data": {
        "filename": "favicon.png"
    }
}
```

---

#### DELETE /branding/favicon (Admin Only)

Delete custom favicon (reverts to default).

**Success Response:**

```json
{
    "status": "success",
    "message": "Favicon deleted successfully"
}
```

---

### Backgrounds

#### GET /branding/backgrounds

List all available background images/videos.

**Response:**

```json
{
    "status": "success",
    "data": {
        "backgrounds": [
            {
                "id": "sunset.jpg",
                "filename": "sunset.jpg",
                "type": "image",
                "url": "http://example.com/api/app/v1/branding/backgrounds/sunset.jpg",
                "thumbnail_url": "http://example.com/api/app/v1/branding/backgrounds/sunset.jpg/thumb"
            },
            {
                "id": "video.mp4",
                "filename": "video.mp4",
                "type": "video",
                "url": "http://example.com/api/app/v1/branding/backgrounds/video.mp4",
                "thumbnail_url": "http://example.com/api/app/v1/branding/backgrounds/video.mp4/thumb"
            }
        ],
        "slideshow_speed": 180,
        "use_custom_backgrounds": true
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `backgrounds` | `[Background]` | Array of background objects |
| `slideshow_speed` | `Int` | Slideshow speed in seconds |
| `use_custom_backgrounds` | `Bool` | Whether custom backgrounds are enabled |

**Background Object:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | `String` | URL-encoded filename (use for API calls) |
| `filename` | `String` | Original filename |
| `type` | `String` | `"image"` or `"video"` |
| `url` | `String` | Full URL to download the background |
| `thumbnail_url` | `String` | Full URL to download thumbnail |

---

#### GET /branding/backgrounds/{id}

Get a specific background file.

**Response:** Binary image/video data with appropriate `Content-Type`

---

#### GET /branding/backgrounds/{id}/thumb

Get thumbnail for a background.

**Response:** Binary image data with `Content-Type: image/webp`

---

#### POST /branding/backgrounds (Admin Only)

Upload a new background image or video.

**Request:** `multipart/form-data`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `background` | File | Yes | Image (jpg, jpeg, png, gif, webp) or video (mp4, webm) |

**Success Response:**

```json
{
    "status": "success",
    "message": "Background uploaded successfully",
    "data": {
        "id": "sunset.jpg",
        "filename": "sunset.jpg",
        "type": "image",
        "url": "http://example.com/api/app/v1/branding/backgrounds/sunset.jpg",
        "thumbnail_url": "http://example.com/api/app/v1/branding/backgrounds/sunset.jpg/thumb"
    }
}
```

---

#### DELETE /branding/backgrounds/{id} (Admin Only)

Delete a background.

**Success Response:**

```json
{
    "status": "success",
    "message": "Background deleted successfully"
}
```

**Not Found Response (HTTP 404):**

```json
{
    "status": "error",
    "message": "Background not found"
}
```

---

## SMTP Settings

Manage SMTP/mail server configuration for sending emails.

### GET /settings/smtp

Returns all SMTP settings. The password is always masked for security.

**Response:**

```json
{
    "status": "success",
    "data": {
        "smtp_host": "in-v3.mailjet.com",
        "smtp_port": 587,
        "smtp_encryption": "tls",
        "smtp_username": "cd144af2c32e10060c56895e72ca0e4b",
        "smtp_password": "********",
        "smtp_sender_name": "Erugo",
        "smtp_sender_address": "erugo@send.yixe.co.uk"
    }
}
```

**Field Types:**

| Field | Type | Description |
|-------|------|-------------|
| `smtp_host` | `String?` | SMTP server hostname (nullable) |
| `smtp_port` | `Int?` | SMTP server port (nullable, 1-65535) |
| `smtp_encryption` | `String` | Encryption mode: `"none"`, `"tls"`, or `"ssl"` |
| `smtp_username` | `String?` | SMTP authentication username (nullable) |
| `smtp_password` | `String?` | Always returns `"********"` when set, `null` when not set |
| `smtp_sender_name` | `String?` | Display name for sent emails (nullable) |
| `smtp_sender_address` | `String?` | Email address for sent emails (nullable) |

---

### PUT /settings/smtp

Update SMTP settings. Supports partial updates - only include fields you want to change.

**Password Handling:** If you send `"********"` as the password value, the existing password is preserved (not overwritten). To clear the password, send `null` or an empty string.

**Request Body:**

All fields are optional. Include only the fields you want to update.

```json
{
    "smtp_host": "smtp.example.com",
    "smtp_port": 587,
    "smtp_encryption": "tls"
}
```

**Validation Rules:**

| Field | Rules |
|-------|-------|
| `smtp_host` | String, max 255 characters, nullable |
| `smtp_port` | Integer, 1-65535, nullable |
| `smtp_encryption` | One of: `"none"`, `"tls"`, `"ssl"` |
| `smtp_username` | String, max 255 characters, nullable |
| `smtp_password` | String, max 255 characters, nullable |
| `smtp_sender_name` | String, max 255 characters, nullable |
| `smtp_sender_address` | Valid email address, max 255 characters, nullable |

**Success Response:**

Returns the complete updated settings (same format as GET, with password masked).

```json
{
    "status": "success",
    "data": {
        "smtp_host": "smtp.example.com",
        "smtp_port": 587,
        "smtp_encryption": "tls",
        "smtp_username": "user@example.com",
        "smtp_password": "********",
        "smtp_sender_name": "My App",
        "smtp_sender_address": "noreply@example.com"
    }
}
```

**Validation Error Example:**

```json
{
    "message": "The selected smtp encryption is invalid.",
    "errors": {
        "smtp_encryption": [
            "The selected smtp encryption is invalid."
        ]
    }
}
```

---

## Additional Settings Groups (Coming Soon)

The following settings groups will be added in future updates:

- **Email Notifications** (`/settings/email-notifications`) - Notification toggles
- **Auth Settings** (`/settings/auth`) - Self-registration settings

---

## Swift Type Definitions

```swift
// MARK: - Shares Settings

struct SharesSettings: Codable {
    let maxShareSize: Int
    let maxShareSizeUnit: ShareSizeUnit
    let defaultExpiryTime: Int
    let maxExpiryTime: Int
    let expiryWarningDays: Int
    let deletionWarningDays: Int
    let cleanFilesAfterDays: Int
    let allowReverseShares: Bool
    let shareUrlMode: ShareUrlMode
    let shareUrlPattern: String
    let defaultUploadMode: UploadMode
    let allowDirectUploads: Bool
    let allowChunkedUploads: Bool
    
    enum CodingKeys: String, CodingKey {
        case maxShareSize = "max_share_size"
        case maxShareSizeUnit = "max_share_size_unit"
        case defaultExpiryTime = "default_expiry_time"
        case maxExpiryTime = "max_expiry_time"
        case expiryWarningDays = "expiry_warning_days"
        case deletionWarningDays = "deletion_warning_days"
        case cleanFilesAfterDays = "clean_files_after_days"
        case allowReverseShares = "allow_reverse_shares"
        case shareUrlMode = "share_url_mode"
        case shareUrlPattern = "share_url_pattern"
        case defaultUploadMode = "default_upload_mode"
        case allowDirectUploads = "allow_direct_uploads"
        case allowChunkedUploads = "allow_chunked_uploads"
    }
}

enum ShareSizeUnit: String, Codable {
    case mb = "MB"
    case gb = "GB"
    case tb = "TB"
}

enum ShareUrlMode: String, Codable {
    case haiku = "haiku"
    case pattern = "pattern"
}

enum UploadMode: String, Codable {
    case chunked = "chunked"
    case direct = "direct"
}

struct UpdateSharesSettingsRequest: Codable {
    var maxShareSize: Int?
    var maxShareSizeUnit: ShareSizeUnit?
    var defaultExpiryTime: Int?
    var maxExpiryTime: Int?
    var expiryWarningDays: Int?
    var deletionWarningDays: Int?
    var cleanFilesAfterDays: Int?
    var allowReverseShares: Bool?
    var shareUrlMode: ShareUrlMode?
    var shareUrlPattern: String?
    var defaultUploadMode: UploadMode?
    var allowDirectUploads: Bool?
    var allowChunkedUploads: Bool?
    
    enum CodingKeys: String, CodingKey {
        case maxShareSize = "max_share_size"
        case maxShareSizeUnit = "max_share_size_unit"
        case defaultExpiryTime = "default_expiry_time"
        case maxExpiryTime = "max_expiry_time"
        case expiryWarningDays = "expiry_warning_days"
        case deletionWarningDays = "deletion_warning_days"
        case cleanFilesAfterDays = "clean_files_after_days"
        case allowReverseShares = "allow_reverse_shares"
        case shareUrlMode = "share_url_mode"
        case shareUrlPattern = "share_url_pattern"
        case defaultUploadMode = "default_upload_mode"
        case allowDirectUploads = "allow_direct_uploads"
        case allowChunkedUploads = "allow_chunked_uploads"
    }
}

// MARK: - General Settings

struct GeneralSettings: Codable {
    let applicationUrl: String
    let defaultLanguage: Language
    let showLanguageSelector: Bool
    
    enum CodingKeys: String, CodingKey {
        case applicationUrl = "application_url"
        case defaultLanguage = "default_language"
        case showLanguageSelector = "show_language_selector"
    }
}

enum Language: String, Codable {
    case en = "en"
    case de = "de"
    case fr = "fr"
    case it = "it"
    case nl = "nl"
    case pt = "pt"
    case ptBR = "pt-BR"
}

struct UpdateGeneralSettingsRequest: Codable {
    var applicationUrl: String?
    var defaultLanguage: Language?
    var showLanguageSelector: Bool?
    
    enum CodingKeys: String, CodingKey {
        case applicationUrl = "application_url"
        case defaultLanguage = "default_language"
        case showLanguageSelector = "show_language_selector"
    }
}

// MARK: - Branding Settings

struct BrandingSettings: Codable {
    let applicationName: String
    let loginMessage: String?
    let logoWidth: Int
    let cssPrimaryColor: String
    let cssSecondaryColor: String
    let cssAccentColor: String
    let cssAccentColorLight: String
    let useMyBackgrounds: Bool
    let backgroundSlideshowSpeed: Int
    let showPoweredBy: Bool
    
    enum CodingKeys: String, CodingKey {
        case applicationName = "application_name"
        case loginMessage = "login_message"
        case logoWidth = "logo_width"
        case cssPrimaryColor = "css_primary_color"
        case cssSecondaryColor = "css_secondary_color"
        case cssAccentColor = "css_accent_color"
        case cssAccentColorLight = "css_accent_color_light"
        case useMyBackgrounds = "use_my_backgrounds"
        case backgroundSlideshowSpeed = "background_slideshow_speed"
        case showPoweredBy = "show_powered_by"
    }
}

struct UpdateBrandingSettingsRequest: Codable {
    var applicationName: String?
    var loginMessage: String?
    var logoWidth: Int?
    var cssPrimaryColor: String?
    var cssSecondaryColor: String?
    var cssAccentColor: String?
    var cssAccentColorLight: String?
    var useMyBackgrounds: Bool?
    var backgroundSlideshowSpeed: Int?
    var showPoweredBy: Bool?
    
    enum CodingKeys: String, CodingKey {
        case applicationName = "application_name"
        case loginMessage = "login_message"
        case logoWidth = "logo_width"
        case cssPrimaryColor = "css_primary_color"
        case cssSecondaryColor = "css_secondary_color"
        case cssAccentColor = "css_accent_color"
        case cssAccentColorLight = "css_accent_color_light"
        case useMyBackgrounds = "use_my_backgrounds"
        case backgroundSlideshowSpeed = "background_slideshow_speed"
        case showPoweredBy = "show_powered_by"
    }
}

// MARK: - SMTP Settings

struct SmtpSettings: Codable {
    let smtpHost: String?
    let smtpPort: Int?
    let smtpEncryption: SmtpEncryption
    let smtpUsername: String?
    let smtpPassword: String?
    let smtpSenderName: String?
    let smtpSenderAddress: String?
    
    enum CodingKeys: String, CodingKey {
        case smtpHost = "smtp_host"
        case smtpPort = "smtp_port"
        case smtpEncryption = "smtp_encryption"
        case smtpUsername = "smtp_username"
        case smtpPassword = "smtp_password"
        case smtpSenderName = "smtp_sender_name"
        case smtpSenderAddress = "smtp_sender_address"
    }
}

enum SmtpEncryption: String, Codable {
    case none = "none"
    case tls = "tls"
    case ssl = "ssl"
}

struct UpdateSmtpSettingsRequest: Codable {
    var smtpHost: String?
    var smtpPort: Int?
    var smtpEncryption: SmtpEncryption?
    var smtpUsername: String?
    var smtpPassword: String?
    var smtpSenderName: String?
    var smtpSenderAddress: String?
    
    enum CodingKeys: String, CodingKey {
        case smtpHost = "smtp_host"
        case smtpPort = "smtp_port"
        case smtpEncryption = "smtp_encryption"
        case smtpUsername = "smtp_username"
        case smtpPassword = "smtp_password"
        case smtpSenderName = "smtp_sender_name"
        case smtpSenderAddress = "smtp_sender_address"
    }
}

// MARK: - Branding Assets

struct FaviconStatus: Codable {
    let hasCustomFavicon: Bool
    let filename: String?
    
    enum CodingKeys: String, CodingKey {
        case hasCustomFavicon = "has_custom_favicon"
        case filename
    }
}

struct BackgroundsResponse: Codable {
    let backgrounds: [Background]
    let slideshowSpeed: Int
    let useCustomBackgrounds: Bool
    
    enum CodingKeys: String, CodingKey {
        case backgrounds
        case slideshowSpeed = "slideshow_speed"
        case useCustomBackgrounds = "use_custom_backgrounds"
    }
}

struct Background: Codable, Identifiable {
    let id: String
    let filename: String
    let type: BackgroundType
    let url: String
    let thumbnailUrl: String
    
    enum CodingKeys: String, CodingKey {
        case id
        case filename
        case type
        case url
        case thumbnailUrl = "thumbnail_url"
    }
}

enum BackgroundType: String, Codable {
    case image = "image"
    case video = "video"
}

struct BackgroundUploadResponse: Codable {
    let id: String
    let filename: String
    let type: BackgroundType
    let url: String
    let thumbnailUrl: String
    
    enum CodingKeys: String, CodingKey {
        case id
        case filename
        case type
        case url
        case thumbnailUrl = "thumbnail_url"
    }
}

struct FaviconUploadResponse: Codable {
    let filename: String
}

// MARK: - API Response Wrappers

struct SettingsResponse<T: Codable>: Codable {
    let status: String
    let data: T
}

struct MessageResponse: Codable {
    let status: String
    let message: String
}

struct ValidationErrorResponse: Codable {
    let message: String
    let errors: [String: [String]]
}
```
