<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;

class EmailConfigService
{

  private $settingService;
  private $settings;

  public function __construct()
  {
    $this->settingService = new SettingsService();
    $this->settings = $this->settingService->getMailSettings();
  }

  public function configureMailer()
  {
    $settings = $this->settings;

    // Laravel's SMTP transport expects null for no encryption, not an empty string
    $encryption = $settings['encryption'];
    if ($encryption === '' || $encryption === null) {
      $encryption = null;
    }

    $config = [
      'transport' => 'smtp',
      'host' => $settings['host'],
      'port' => $settings['port'],
      'encryption' => $encryption,
      'timeout' => null,
    ];

    // Only add authentication credentials if they are actually provided
    // This allows SMTP relays that don't require authentication to work
    if (!empty($settings['username']) && !empty($settings['password'])) {
      $config['username'] = $settings['username'];
      $config['password'] = $settings['password'];
    }

    $mailer = Mail::build($config);

    return $mailer;
  }

  public function configureMailable(Mailable $mailable)
  {
    $mailable->from($this->settings['from_address'], $this->settings['from_name']);
    return $mailable;
  }
}
