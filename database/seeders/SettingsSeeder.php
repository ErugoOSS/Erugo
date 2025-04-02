<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->insertOrIgnore([

            [
                'key' => 'application_name',
                'value' => 'Erugo File Sharing',
                'previous_value' => 'Erugo File Sharing',
                'group' => 'ui.strings',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'css_primary_color',
                'value' => '#589db6',
                'previous_value' => '#589db6',
                'group' => 'ui.css',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'css_secondary_color',
                'value' => '#01021c',
                'previous_value' => '#01021c',
                'group' => 'ui.css',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'css_accent_color',
                'value' => '#63a8bc',
                'previous_value' => '#63a8bc',
                'group' => 'ui.css',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'css_accent_color_light',
                'value' => '#d0e1d5',
                'previous_value' => '#d0e1d5',
                'group' => 'ui.css',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'logo',
                'value' => 'logo.png',
                'previous_value' => null,
                'group' => 'ui.logo',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'logo_width',
                'value' => '100',
                'previous_value' => '100',
                'group' => 'ui.logo',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'use_my_backgrounds',
                'value' => 'false',
                'previous_value' => 'false',
                'group' => 'ui',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'background_slideshow_speed',
                'value' => '180',
                'previous_value' => '180',
                'group' => 'ui',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'show_powered_by',
                'value' => 'true',
                'previous_value' => 'true',
                'group' => 'ui',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'application_url',
                'value' => 'http://localhost:9199',
                'previous_value' => null,
                'group' => 'system',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'login_message',
                'value' => 'Login to your account to get started.',
                'previous_value' => null,
                'group' => 'ui.strings',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'max_expiry_time',
                'value' => '10',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'default_expiry_time',
                'value' => '7',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'expiry_warning_days',
                'value' => '3',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'deletion_warning_days',
                'value' => '7',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'max_share_size',
                'value' => '2',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'max_share_size_unit',
                'value' => 'GB',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'clean_files_after_days',
                'value' => '30',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'default_upload_mode',
                'value' => 'chunked',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'allow_direct_uploads',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'allow_chunked_uploads',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'smtp_host',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.smtp',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'smtp_port',
                'value' => '587',
                'previous_value' => null,
                'group' => 'system.smtp',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'smtp_encryption',
                'value' => 'tls',
                'previous_value' => null,
                'group' => 'system.smtp',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'smtp_username',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.smtp',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'smtp_password',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.smtp',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'smtp_sender_name',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.smtp',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'smtp_sender_address',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.smtp',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'emails_share_downloaded_enabled',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.emails',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'emails_share_expiry_warning_enabled',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.emails',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'emails_share_expired_warning_enabled',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.emails',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'emails_share_deletion_warning_enabled',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.emails',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'emails_share_deleted_enabled',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.emails',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'default_language',
                'value' => 'en',
                'previous_value' => null,
                'group' => 'system',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'show_language_selector',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system',
                'created_at' => now(),
                'updated_at' => now()
            ],
            //allow reverse shares
            [
                'key' => 'allow_reverse_shares',
                'value' => 'true',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ],

            //email subjects
            [
                'key' => 'email_subject_accountCreatedMail.twig',
                'value' => 'Account Created! Set your password',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_passwordResetMail.twig',
                'value' => 'Reset your password',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_reverseShareInviteMail.twig',
                'value' => 'You have been invited to share files',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_shareCreatedMail.twig',
                'value' => 'Files have been shared with you',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_shareDeletedWarningMail.twig',
                'value' => 'Your share has been deleted',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_shareDeletionWarningMail.twig',
                'value' => 'Your share will be deleted soon',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_shareDownloadedMail.twig',
                'value' => 'Your share has been downloaded',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_shareExpiredWarningMail.twig',
                'value' => 'Your share has expired',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_subject_shareExpiryWarningMail.twig',
                'value' => 'Your share will expire soon',
                'previous_value' => null,
                'group' => 'system.emails.subjects',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'email_template_fallback_text',
                'value' => 'If you\'re having trouble with the button above, copy and paste the URL below into your web browser.',
                'previous_value' => null,
                'group' => 'system.emails',
                'created_at' => now(),
                'updated_at' => now()
            ]

        ]);
    }
}
