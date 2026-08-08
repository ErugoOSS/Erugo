<?php

namespace App\Settings;

use App\Models\User;
use Illuminate\Support\Collection;

final class SettingAccess
{
    /**
     * Explicit visibility for every seeded setting key.
     * Unknown keys default to Admin (fail closed for non-admins).
     *
     * @var array<string, SettingVisibility>
     */
    private const MAP = [
        // ui.strings
        'application_name' => SettingVisibility::Public,
        'login_message' => SettingVisibility::Public,

        // ui.css
        'css_primary_color' => SettingVisibility::Public,
        'css_secondary_color' => SettingVisibility::Public,
        'css_accent_color' => SettingVisibility::Public,
        'css_accent_color_light' => SettingVisibility::Public,

        // ui.logo
        'logo' => SettingVisibility::Public,
        'logo_width' => SettingVisibility::Public,

        // ui
        'use_my_backgrounds' => SettingVisibility::Public,
        'background_slideshow_speed' => SettingVisibility::Public,
        'show_powered_by' => SettingVisibility::Public,

        // system
        'application_url' => SettingVisibility::Admin,
        'default_language' => SettingVisibility::Public,
        'show_language_selector' => SettingVisibility::Public,

        // system.shares
        'max_expiry_time' => SettingVisibility::Public,
        'default_expiry_time' => SettingVisibility::Public,
        'expiry_warning_days' => SettingVisibility::Admin,
        'deletion_warning_days' => SettingVisibility::Admin,
        'max_share_size' => SettingVisibility::Public,
        'max_share_size_unit' => SettingVisibility::Public,
        'clean_files_after_days' => SettingVisibility::Admin,
        'default_upload_mode' => SettingVisibility::Public,
        'allow_direct_uploads' => SettingVisibility::Public,
        'allow_chunked_uploads' => SettingVisibility::Public,
        'allow_reverse_shares' => SettingVisibility::Public,
        'share_url_mode' => SettingVisibility::Admin,
        'share_url_pattern' => SettingVisibility::Admin,

        // system.smtp — Secret: credentials / mail identity (same read rules as Admin today)
        'smtp_host' => SettingVisibility::Secret,
        'smtp_port' => SettingVisibility::Secret,
        'smtp_encryption' => SettingVisibility::Secret,
        'smtp_username' => SettingVisibility::Secret,
        'smtp_password' => SettingVisibility::Secret,
        'smtp_sender_name' => SettingVisibility::Secret,
        'smtp_sender_address' => SettingVisibility::Secret,

        // system.emails
        'emails_share_downloaded_enabled' => SettingVisibility::Admin,
        'emails_share_expiry_warning_enabled' => SettingVisibility::Admin,
        'emails_share_expired_warning_enabled' => SettingVisibility::Admin,
        'emails_share_deletion_warning_enabled' => SettingVisibility::Admin,
        'emails_share_deleted_enabled' => SettingVisibility::Admin,
        'email_template_fallback_text' => SettingVisibility::Admin,

        // system.emails.subjects
        'email_subject_accountCreatedMail.twig' => SettingVisibility::Admin,
        'email_subject_passwordResetMail.twig' => SettingVisibility::Admin,
        'email_subject_reverseShareInviteMail.twig' => SettingVisibility::Admin,
        'email_subject_shareCreatedMail.twig' => SettingVisibility::Admin,
        'email_subject_shareDeletedWarningMail.twig' => SettingVisibility::Admin,
        'email_subject_shareDeletionWarningMail.twig' => SettingVisibility::Admin,
        'email_subject_shareDownloadedMail.twig' => SettingVisibility::Admin,
        'email_subject_shareExpiredWarningMail.twig' => SettingVisibility::Admin,
        'email_subject_shareExpiryWarningMail.twig' => SettingVisibility::Admin,
        'email_subject_emailVerificationMail.twig' => SettingVisibility::Admin,
        'email_subject_liveshareInviteMail.twig' => SettingVisibility::Admin,

        // system.auth
        'self_registration_enabled' => SettingVisibility::Public,
        'self_registration_allow_any_domain' => SettingVisibility::Admin,
        'self_registration_allowed_domains' => SettingVisibility::Admin,

        // system.licence — Secret: licence material (same read rules as Admin today)
        'licence' => SettingVisibility::Secret,

        // system.liveshares
        'liveshares_enabled' => SettingVisibility::Public,
        'liveshares_max_size' => SettingVisibility::Public,
        'liveshares_max_size_unit' => SettingVisibility::Public,
        'liveshares_max_files_per_user' => SettingVisibility::Public,
    ];

    public static function visibility(string $key): SettingVisibility
    {
        return self::MAP[$key] ?? SettingVisibility::Admin;
    }

    public static function canRead(?User $user, string $key): bool
    {
        if ($user?->admin) {
            return true;
        }

        // Public only for non-admins. Admin and Secret are both privileged today;
        // Secret is reserved for stricter handling later (see SettingVisibility::Secret).
        return self::visibility($key) === SettingVisibility::Public;
    }

    /**
     * @param  Collection<int, \App\Models\Setting>  $settings
     * @return Collection<int, \App\Models\Setting>
     */
    public static function filterReadable(?User $user, Collection $settings): Collection
    {
        if ($user?->admin) {
            return $settings->values();
        }

        return $settings
            ->filter(fn ($setting) => self::canRead($user, $setting->key))
            ->values();
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::MAP);
    }
}
