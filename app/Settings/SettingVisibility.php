<?php

namespace App\Settings;

enum SettingVisibility: string
{
    case Public = 'public';
    case Admin = 'admin';

    /**
     * Credentials and other highly sensitive values.
     * Currently enforced the same as Admin (admin-only reads); kept distinct
     * so we can apply stricter handling later (e.g. masking, audit logging,
     * or excluding from bulk exports) without reclassifying every key.
     */
    case Secret = 'secret';
}
