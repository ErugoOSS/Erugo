<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Theme;

class ThemeSanitizationTest extends TestCase
{
  /**
   * Build a Theme model with a raw theme payload, without touching the database.
   */
  private function theme(array $theme): Theme
  {
    $model = new Theme();
    $model->setRawAttributes([
      'name' => 'Test Theme',
      'theme' => json_encode($theme),
    ], true);

    return $model;
  }

  private function minimalTheme(array $extra = []): array
  {
    return array_merge([
      'links' => [
        'default' => 'rgb(0, 0, 0)',
        'hover' => 'rgb(1, 1, 1)',
        'active' => 'rgb(2, 2, 2)',
        'disabled' => 'rgb(3, 3, 3)',
      ],
      'buttons' => [
        'primary' => [
          'default' => ['background' => 'rgb(10, 10, 10)', 'text' => 'rgb(255, 255, 255)'],
          'hover' => ['background' => 'rgb(20, 20, 20)', 'text' => 'rgb(255, 255, 255)'],
          'active' => ['background' => 'rgb(30, 30, 30)', 'text' => 'rgb(255, 255, 255)'],
        ],
        'secondary' => [
          'default' => ['background' => 'rgb(40, 40, 40)', 'text' => 'rgb(255, 255, 255)'],
          'hover' => ['background' => 'rgb(50, 50, 50)', 'text' => 'rgb(255, 255, 255)'],
          'active' => ['background' => 'rgb(60, 60, 60)', 'text' => 'rgb(255, 255, 255)'],
        ],
      ],
    ], $extra);
  }

  public function test_background_size_and_position_are_passed_through()
  {
    $theme = $this->theme($this->minimalTheme([
      'background' => [
        'imageSize' => 'cover',
        'imagePosition' => 'center center',
      ],
    ]))->theme;

    $this->assertSame('cover', $theme->background->imageSize);
    $this->assertSame('center center', $theme->background->imagePosition);
  }

  public function test_background_accepts_composite_sizes_and_positions()
  {
    $theme = $this->theme($this->minimalTheme([
      'background' => [
        'imageSize' => '100% auto',
        'imagePosition' => 'center TOP',
        'mobile' => [
          'imageSize' => 'contain',
          'imagePosition' => '50% 25%',
        ],
      ],
    ]))->theme;

    $this->assertSame('100% auto', $theme->background->imageSize);
    $this->assertSame('center top', $theme->background->imagePosition);
    $this->assertSame('contain', $theme->background->mobile->imageSize);
    $this->assertSame('50% 25%', $theme->background->mobile->imagePosition);
  }

  public function test_background_size_rejects_declaration_injection()
  {
    $theme = $this->theme($this->minimalTheme([
      'background' => [
        'imageSize' => 'cover; } body { display: none',
        'imagePosition' => 'center; background-image: url(//evil.test/x.png)',
        'mobile' => [
          'imageSize' => 'url(//evil.test/x.png)',
        ],
      ],
    ]))->theme;

    $this->assertSame('cover', $theme->background->imageSize);
    $this->assertSame('center center', $theme->background->imagePosition);
    $this->assertSame('cover', $theme->background->mobile->imageSize);
  }

  public function test_font_family_is_restricted_to_plain_stacks()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => [
        'body' => "'IBM Plex Sans', sans-serif",
        'heading' => 'Outfit; } body { display: none',
      ],
    ]))->theme;

    $this->assertSame('IBM Plex Sans, sans-serif', $theme->fonts->body);
    $this->assertSame('Outfit, sans-serif', $theme->fonts->heading);
  }

  public function test_font_import_url_on_allowlisted_host_is_kept_intact()
  {
    $url = 'https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap';

    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => $url],
    ]))->theme;

    $this->assertSame($url, $theme->fonts->importUrl);
  }

  public function test_font_import_url_on_unknown_host_is_removed()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => 'https://evil.test/font.css'],
    ]))->theme;

    $this->assertSame('[url-removed]', $theme->fonts->importUrl);
  }

  public function test_font_import_url_requires_https()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => 'http://fonts.googleapis.com/css2?family=Inter'],
    ]))->theme;

    $this->assertSame('[url-removed]', $theme->fonts->importUrl);
  }

  public function test_font_import_url_rejects_javascript_and_protocol_relative()
  {
    $js = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => 'javascript:alert(1)'],
    ]))->theme;

    $protocolRelative = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => '//fonts.googleapis.com/css2?family=Inter'],
    ]))->theme;

    $this->assertStringNotContainsString('javascript', $js->fonts->importUrl);
    $this->assertSame('[url-removed]', $protocolRelative->fonts->importUrl);
  }

  public function test_same_origin_font_stylesheet_path_is_allowed()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => '/fonts/myfont.css'],
    ]))->theme;

    $this->assertSame('/fonts/myfont.css', $theme->fonts->importUrl);
  }

  public function test_image_size_rejects_script_url()
  {
    $theme = $this->theme($this->minimalTheme([
      'background' => ['imageSize' => 'url(javascript:alert(1))'],
    ]))->theme;

    $this->assertSame('cover', $theme->background->imageSize);
  }

  public function test_themes_without_new_keys_are_unchanged()
  {
    $theme = $this->theme($this->minimalTheme())->theme;

    $this->assertFalse(isset($theme->background));
    $this->assertFalse(isset($theme->fonts));
  }
}
