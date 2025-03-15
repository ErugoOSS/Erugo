<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
  public function get($key)
  {
    $setting = Setting::where('key', $key)->first();
    if (!$setting) {
      return null;
    }
    return $this->convertToCorrectType($setting->value);
  }

  public function convertToCorrectType($value)
  {

    if (is_array($value)) {
      $value = (object)$value;
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
    return Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
  }

  public function getGlobalViewData()
  {
    $required_keys = [
      'application_name',
      'application_url',
      'login_message',
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
