<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
  private const CACHE_KEY = 'settings_all';

  /**
   * Get all settings from cache, or load from database if not cached.
   */
  protected function getAllCached(): array
  {
    return Cache::rememberForever(self::CACHE_KEY, function () {
      return Setting::pluck('value', 'key')->toArray();
    });
  }

  /**
   * Clear the settings cache. Call this after settings are modified.
   */
  public function clearCache(): void
  {
    Cache::forget(self::CACHE_KEY);
  }

  public function get($key)
  {
    $settings = $this->getAllCached();
    if (!array_key_exists($key, $settings)) {
      return null;
    }
    return $this->convertToCorrectType($settings[$key]);
  }

  public function convertToCorrectType($value)
  {

    if (is_array($value)) {
      $value = (object)$value;
    }

    if ($value === 'null' || $value === null) {
      return null;
    }
    if ($value === 'true') {
      return true;
    }
    if ($value === 'false') {
      return false;
    }
    if ($value === false) {
      return false;
    }
    if ($value === true) {
      return true;
    }
    if (is_numeric($value)) {
      return (int)$value;
    }
    if (is_float($value)) {
      return (float)$value;
    }
    return $value;
  }

  public function getMany($keys)
  {
    $settings = $this->getAllCached();
    return array_intersect_key($settings, array_flip($keys));
  }

  public function getGlobalViewData()
  {
    $required_keys = [
      'application_name',
      'application_url',
      'login_message',
      'email_template_fallback_text',
    ];

    return $this->getMany($required_keys);
  }

  public function getMaxUploadSize()
  {
    $max_upload_size = $this->get('max_share_size');
    $max_upload_size_unit = $this->get('max_share_size_unit');
    if ($max_upload_size_unit && $max_upload_size) {
      if ($max_upload_size_unit == 'MB') {
        return $max_upload_size * 1024 * 1024;
      } else if ($max_upload_size_unit == 'GB') {
        return $max_upload_size * 1024 * 1024 * 1024;
      }
    }
    return null;
  }

  public function getMailSettings()
  {

    $settings = $this->getMany([
      'smtp_host',
      'smtp_port',
      'smtp_username',
      'smtp_password',
      'smtp_encryption',
      'smtp_sender_address',
      'smtp_sender_name',
    ]);

    return [
      'host' => $settings['smtp_host'],
      'port' => $settings['smtp_port'],
      'username' => $settings['smtp_username'],
      'password' => $settings['smtp_password'],
      'encryption' => $settings['smtp_encryption'],
      'from_address' => $settings['smtp_sender_address'],
      'from_name' => $settings['smtp_sender_name'],
    ];
  }
}
