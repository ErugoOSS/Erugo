<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShareStatusViewsTest extends TestCase
{
    public function test_settings_with_quotes_stay_inside_the_data_attribute(): void
    {
        $this->withoutVite();

        $settings = [
            'application_name' => "Erugo' onmouseover='alert(1)",
            'css_primary_color' => '#000',
            'css_secondary_color' => '#111',
            'css_accent_color' => '#222',
            'css_accent_color_light' => '#333',
        ];

        foreach (['shares.pending', 'shares.failed'] as $view) {
            $html = view($view, ['settings' => $settings])->render();

            preg_match("/<body data-settings='([^']*)'>/", $html, $match);
            $this->assertNotEmpty($match, "$view: data-settings attribute not closed where expected");

            $decoded = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
            $this->assertSame($settings['application_name'], $decoded['application_name'], $view);
            $this->assertStringNotContainsString("' onmouseover='", $html, $view);
        }
    }
}
