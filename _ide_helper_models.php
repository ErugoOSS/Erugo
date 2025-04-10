<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $provider_class
 * @property object|null $provider_config
 * @property bool $enabled
 * @property bool $allow_registration
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $icon
 * @property string|null $uuid
 * @property bool $trust_email
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereAllowRegistration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereProviderClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereProviderConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereTrustEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthProvider whereUuid($value)
 */
	class AuthProvider extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $upload_session_id
 * @property int $chunk_index
 * @property int $chunk_size
 * @property string $chunk_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\UploadSession $uploadSession
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload whereChunkIndex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload whereChunkPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload whereChunkSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkUpload whereUploadSessionId($value)
 */
	class ChunkUpload extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property bool $use_for_shares
 * @property string $driver
 * @property string|null $root
 * @property string|null $key
 * @property string|null $secret
 * @property string|null $region
 * @property string|null $bucket
 * @property string|null $url
 * @property string|null $endpoint
 * @property bool $use_path_style_endpoint
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereBucket($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereRoot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereUseForShares($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Disk whereUsePathStyleEndpoint($value)
 */
	class Disk extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $share_id
 * @property string $ip_address
 * @property string $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Share $share
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download byIpAddress($ipAddress)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download byShare($share)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download byUser($user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download byUserAgent($userAgent)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download whereShareId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Download whereUserAgent($value)
 */
	class Download extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $share_id
 * @property string $name
 * @property int $size
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $temp_path
 * @property string|null $full_path
 * @property-read \App\Models\Share|null $share
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereFullPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereShareId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereTempPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereUpdatedAt($value)
 */
	class File extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $guest_user_id
 * @property string $recipient_name
 * @property string $recipient_email
 * @property string|null $message
 * @property string|null $used_at
 * @property string|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $guestUser
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereGuestUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereRecipientEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereRecipientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReverseShareInvite whereUserId($value)
 */
	class ReverseShareInvite extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string|null $previous_value
 * @property string $group
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting wherePreviousValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 */
	class Setting extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $description
 * @property string $path
 * @property string|null $password
 * @property string $long_id
 * @property int $size
 * @property int $file_count
 * @property int|null $download_limit
 * @property int $download_count
 * @property int $require_email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string $status
 * @property int $sent_expiry_warning
 * @property int $sent_expired
 * @property int $sent_deletion_warning
 * @property int $sent_deleted
 * @property int $public
 * @property int|null $invite_id
 * @property int|null $disk_id
 * @property-read \App\Models\Disk|null $disk
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\File> $files
 * @property-read int|null $files_count
 * @property-read mixed $deleted
 * @property-read mixed $deletes_at
 * @property-read mixed $expired
 * @property-read mixed $share_disk
 * @property-read \App\Models\ReverseShareInvite|null $invite
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share readyForCleaning()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereDiskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereDownloadCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereDownloadLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereFileCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereInviteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereLongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share wherePublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereRequireEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereSentDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereSentDeletionWarning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereSentExpired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereSentExpiryWarning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share whereUserId($value)
 */
	class Share extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property object $theme
 * @property int $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $category
 * @property int $bundled
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereBundled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereTheme($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedAt($value)
 */
	class Theme extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $upload_id
 * @property int $user_id
 * @property string $filename
 * @property int $filesize
 * @property string $filetype
 * @property int $total_chunks
 * @property int $chunks_received
 * @property string $status
 * @property int|null $file_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChunkUpload> $chunks
 * @property-read int|null $chunks_count
 * @property-read \App\Models\File|null $file
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereChunksReceived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereFilesize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereFiletype($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereTotalChunks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereUploadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereUserId($value)
 */
	class UploadSession extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $admin
 * @property int $active
 * @property int $must_change_password
 * @property int $is_guest
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuthProvider> $authProviders
 * @property-read int|null $auth_providers_count
 * @property-read mixed $first_name
 * @property-read \App\Models\ReverseShareInvite|null $invite
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReverseShareInvite> $invites
 * @property-read int|null $invites_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Share> $shares
 * @property-read int|null $shares_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsGuest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMustChangePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent implements \PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property int $auth_provider_id
 * @property string $provider_user_id
 * @property string|null $provider_email
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property array<array-key, mixed>|null $provider_data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthProvider $authProvider
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereAuthProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereProviderData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereProviderEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereProviderUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuthProvider whereUserId($value)
 */
	class UserAuthProvider extends \Eloquent {}
}

