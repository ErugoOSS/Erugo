<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBrandingSettingsRequest;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Settings\UpdateSharesSettingsRequest;
use App\Http\Requests\Settings\UpdateSmtpSettingsRequest;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;

class AppSettingsController extends Controller
{
    /**
     * Branding settings span multiple DB groups, so we map each setting to its group.
     */
    private const BRANDING_SETTINGS = [
        'ui.strings' => ['application_name', 'login_message'],
        'ui.logo' => ['logo_width'],
        'ui.css' => ['css_primary_color', 'css_secondary_color', 'css_accent_color', 'css_accent_color_light'],
        'ui' => ['use_my_backgrounds', 'background_slideshow_speed', 'show_powered_by'],
    ];

    /**
     * Boolean fields in branding settings that need type normalization.
     */
    private const BRANDING_BOOLEAN_FIELDS = [
        'use_my_backgrounds',
        'show_powered_by',
    ];

    public function __construct(
        private SettingsRepository $settingsRepository
    ) {}

    /**
     * Get shares settings.
     */
    public function getShares(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->settingsRepository->getGroup('system.shares')
        ]);
    }

    /**
     * Update shares settings.
     */
    public function updateShares(UpdateSharesSettingsRequest $request): JsonResponse
    {
        $this->settingsRepository->updateGroup('system.shares', $request->validated());
        
        return response()->json([
            'status' => 'success',
            'data' => $this->settingsRepository->getGroup('system.shares')
        ]);
    }

    /**
     * Get general settings.
     */
    public function getGeneral(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->settingsRepository->getGroup('system')
        ]);
    }

    /**
     * Update general settings.
     */
    public function updateGeneral(UpdateGeneralSettingsRequest $request): JsonResponse
    {
        $this->settingsRepository->updateGroup('system', $request->validated());
        
        return response()->json([
            'status' => 'success',
            'data' => $this->settingsRepository->getGroup('system')
        ]);
    }

    /**
     * Get branding settings.
     * 
     * Branding settings span multiple DB groups, so we aggregate them here.
     */
    public function getBranding(): JsonResponse
    {
        $result = [];
        
        foreach (self::BRANDING_SETTINGS as $group => $keys) {
            $groupSettings = $this->settingsRepository->getGroup($group);
            foreach ($keys as $key) {
                if (isset($groupSettings[$key])) {
                    $result[$key] = $groupSettings[$key];
                }
            }
        }
        
        // Normalize boolean fields to ensure consistent types
        foreach (self::BRANDING_BOOLEAN_FIELDS as $field) {
            if (array_key_exists($field, $result)) {
                $result[$field] = (bool) $result[$field];
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $result
        ]);
    }

    /**
     * Update branding settings.
     * 
     * Routes each setting to its correct DB group.
     */
    public function updateBranding(UpdateBrandingSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Route each setting to its correct group
        foreach (self::BRANDING_SETTINGS as $group => $keys) {
            $groupData = array_intersect_key($validated, array_flip($keys));
            if (!empty($groupData)) {
                $this->settingsRepository->updateGroup($group, $groupData);
            }
        }
        
        // Return combined result
        return $this->getBranding();
    }

    /**
     * Get SMTP settings.
     */
    public function getSmtp(): JsonResponse
    {
        $settings = $this->settingsRepository->getGroup('system.smtp');
        
        // Mask password for security
        if (isset($settings['smtp_password']) && $settings['smtp_password']) {
            $settings['smtp_password'] = '********';
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Update SMTP settings.
     */
    public function updateSmtp(UpdateSmtpSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // If password is masked placeholder, don't update it
        if (isset($data['smtp_password']) && $data['smtp_password'] === '********') {
            unset($data['smtp_password']);
        }
        
        $this->settingsRepository->updateGroup('system.smtp', $data);
        
        $settings = $this->settingsRepository->getGroup('system.smtp');
        if (isset($settings['smtp_password']) && $settings['smtp_password']) {
            $settings['smtp_password'] = '********';
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Get licence settings.
     */
    public function getLicence(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->settingsRepository->getGroup('system.licence')
        ]);
    }

    /**
     * Update licence settings.
     */
    public function updateLicence(\Illuminate\Http\Request $request): JsonResponse
    {
        $data = $request->validate([
            'licence' => 'nullable|string'
        ]);
        
        $this->settingsRepository->updateGroup('system.licence', $data);
        
        return response()->json([
            'status' => 'success',
            'data' => $this->settingsRepository->getGroup('system.licence')
        ]);
    }
}
