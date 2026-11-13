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

    $this->assertSame('"IBM Plex Sans", sans-serif', $theme->fonts->body);
    $this->assertSame('"Outfit", sans-serif', $theme->fonts->heading);
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

  public function test_same_origin_font_path_keeps_cache_busting_query()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => '/storage/fonts/myfont.css?v=2'],
    ]))->theme;

    $this->assertSame('/storage/fonts/myfont.css?v=2', $theme->fonts->importUrl);
  }

  public function test_same_origin_font_path_with_spaces_is_rejected()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => '/fonts/My Font.css'],
    ]))->theme;

    $this->assertSame('[url-removed]', $theme->fonts->importUrl);
  }

  public function test_font_import_url_keeps_multi_weight_query_intact()
  {
    $url = 'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@400;700&display=swap';

    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['importUrl' => $url],
    ]))->theme;

    $this->assertSame($url, $theme->fonts->importUrl);
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

  public function test_font_family_ending_in_digit_is_quoted()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['body' => 'Source Sans 3, sans-serif', 'heading' => 'Source Sans 3, sans-serif'],
    ]))->theme;

    $this->assertSame('"Source Sans 3", sans-serif', $theme->fonts->body);
    $this->assertSame('"Source Sans 3", sans-serif', $theme->fonts->heading);
  }

  public function test_font_family_keeps_generic_families_unquoted()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['body' => '"My Font", system-ui, sans-serif'],
    ]))->theme;

    $this->assertSame('"My Font", system-ui, sans-serif', $theme->fonts->body);
  }

  public function test_font_family_cannot_inject_css_through_quotes()
  {
    $theme = $this->theme($this->minimalTheme([
      'fonts' => ['body' => 'Evil", sans-serif; } body { display: none'],
    ]))->theme;

    $this->assertSame('"Outfit", sans-serif', $theme->fonts->body);
  }

  public function test_logo_alignment_maps_to_flex_values()
  {
    $cases = [
      'left' => 'flex-start',
      'LEFT' => 'flex-start',
      'center' => 'center',
      'right' => 'flex-end',
      'flex-end' => 'flex-end',
      'end' => 'flex-end',
      'sideways' => 'flex-start',
      'display: none' => 'flex-start',
    ];

    foreach ($cases as $input => $expected) {
      $theme = $this->theme($this->minimalTheme([
        'logo' => ['align' => $input],
      ]))->theme;

      $this->assertSame($expected, $theme->logo->align);
    }
  }

  public function test_logo_mobile_alignment_falls_back_to_desktop_value()
  {
    $theme = $this->theme($this->minimalTheme([
      'logo' => ['align' => 'right', 'mobileAlign' => 'nonsense'],
    ]))->theme;

    $this->assertSame('flex-end', $theme->logo->align);
    $this->assertSame('flex-end', $theme->logo->mobileAlign);
  }

  public function test_logo_mobile_alignment_can_differ_from_desktop()
  {
    $theme = $this->theme($this->minimalTheme([
      'logo' => ['align' => 'left', 'mobileAlign' => 'center'],
    ]))->theme;

    $this->assertSame('flex-start', $theme->logo->align);
    $this->assertSame('center', $theme->logo->mobileAlign);
  }

  public function test_mobile_background_accepts_uploaded_filename()
  {
    $theme = $this->theme($this->minimalTheme([
      'background' => ['mobile' => ['image' => 'bastion mobile-bg_2.webp']],
    ]))->theme;

    $this->assertSame('bastion mobile-bg_2.webp', $theme->background->mobile->image);
  }

  public function test_mobile_background_rejects_paths_and_other_files()
  {
    $rejected = [
      '../../app.key',
      '../backgrounds/x.jpg',
      '/etc/passwd.jpg',
      'C:\temp\x.jpg',
      'frontend.js',
      'shell.php.jpg.txt',
      'x.jpg?cache=1',
      'x.php',
      'noextension',
    ];

    foreach ($rejected as $candidate) {
      $theme = $this->theme($this->minimalTheme([
        'background' => ['mobile' => ['image' => $candidate]],
      ]))->theme;

      $this->assertSame('', $theme->background->mobile->image);
    }
  }

  public function test_mobile_background_accepts_video_uploads()
  {
    $theme = $this->theme($this->minimalTheme([
      'background' => ['mobile' => ['image' => 'loop.mp4']],
    ]))->theme;

    $this->assertSame('loop.mp4', $theme->background->mobile->image);
  }

  public function test_themes_without_logo_block_are_unchanged()
  {
    $theme = $this->theme($this->minimalTheme())->theme;

    $this->assertFalse(isset($theme->logo));
  }
}
