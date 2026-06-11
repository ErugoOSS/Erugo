<?php

namespace App\Mail\Concerns;

use App\Models\Setting;
use App\Services\SettingsService;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

trait RendersSubject
{
    /**
     * Render an email subject stored in settings through Twig, giving it the same
     * variables the email body receives (the Mailable's public properties plus the
     * global `settings` view data). This lets subjects use the same {{ }} placeholders
     * as the body, e.g. {{ share.name }} or {{ sender_name }}.
     */
    protected function renderSubject(string $settingKey): string
    {
        $raw = optional(Setting::where('key', $settingKey)->first())->value ?? '';

        // Public properties of the Mailable, identical to what the body view sees.
        $context = $this->buildViewData();

        try {
            $context['settings'] = app(SettingsService::class)->getGlobalViewData();
        } catch (\Throwable $e) {
            $context['settings'] = [];
        }

        try {
            // autoescape OFF: subjects are plain text, so '&'/'<' must not become entities.
            $twig = new Environment(new ArrayLoader(), ['autoescape' => false]);
            return $twig->createTemplate($raw)->render($context);
        } catch (\Throwable $e) {
            // A malformed template must never break email sending.
            return $raw;
        }
    }
}
