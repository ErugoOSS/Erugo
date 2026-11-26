<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\UiStyleSettings;

class UiStyleSettingsTest extends TestCase
{
  public function test_background_size_accepts_allowlisted_values()
  {
    $this->assertSame('cover', UiStyleSettings::backgroundSize('cover'));
    $this->assertSame('contain', UiStyleSettings::backgroundSize('contain'));
    $this->assertSame('100% auto', UiStyleSettings::backgroundSize('100% auto'));
    $this->assertSame('auto 100%', UiStyleSettings::backgroundSize('  AUTO 100%  '));
  }

  public function test_background_size_rejects_anything_else()
  {
    $this->assertSame('cover', UiStyleSettings::backgroundSize(null));
    $this->assertSame('cover', UiStyleSettings::backgroundSize(''));
    $this->assertSame('cover', UiStyleSettings::backgroundSize('cover; } body { display: none'));
    $this->assertSame('cover', UiStyleSettings::backgroundSize('url(//evil.test/x.png)'));
    $this->assertSame('contain', UiStyleSettings::backgroundSize('nonsense', 'contain'));
  }

  public function test_background_position_accepts_allowlisted_values()
  {
    $this->assertSame('center center', UiStyleSettings::backgroundPosition('center center'));
    $this->assertSame('center top', UiStyleSettings::backgroundPosition('CENTER TOP'));
    $this->assertSame('top left', UiStyleSettings::backgroundPosition(' top   left '));
  }

  public function test_background_position_rejects_anything_else()
  {
    $this->assertSame('center center', UiStyleSettings::backgroundPosition(null));
    $this->assertSame('center center', UiStyleSettings::backgroundPosition('center; background-image: url(//evil.test/x.png)'));
    $this->assertSame('center top', UiStyleSettings::backgroundPosition('bogus', 'center top'));
  }

  public function test_logo_alignment_maps_to_flex_values()
  {
    $this->assertSame('flex-start', UiStyleSettings::logoAlignment('left'));
    $this->assertSame('flex-start', UiStyleSettings::logoAlignment('LEFT'));
    $this->assertSame('center', UiStyleSettings::logoAlignment('center'));
    $this->assertSame('flex-end', UiStyleSettings::logoAlignment('right'));
    $this->assertSame('flex-start', UiStyleSettings::logoAlignment('sideways'));
    $this->assertSame('flex-start', UiStyleSettings::logoAlignment(null));
    $this->assertSame('flex-end', UiStyleSettings::logoAlignment('display: none', 'flex-end'));
  }

  public function test_font_family_quotes_every_named_family()
  {
    $this->assertSame('"Source Sans 3", sans-serif', UiStyleSettings::fontFamily('Source Sans 3, sans-serif'));
    $this->assertSame('"IBM Plex Sans", system-ui, sans-serif', UiStyleSettings::fontFamily("'IBM Plex Sans', system-ui, sans-serif"));
  }

  public function test_font_family_returns_fallback_for_unusable_values()
  {
    $this->assertSame('"Outfit", sans-serif', UiStyleSettings::fontFamily(''));
    $this->assertSame('"Outfit", sans-serif', UiStyleSettings::fontFamily(null));
    $this->assertSame('"Outfit", sans-serif', UiStyleSettings::fontFamily('Evil", sans-serif; } body { display: none'));
    $this->assertNull(UiStyleSettings::fontFamily('', null));
  }

  public function test_stylesheet_url_keeps_google_fonts_urls_intact()
  {
    $url = 'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@400;700&display=swap';
    $this->assertSame($url, UiStyleSettings::stylesheetUrl($url));

    $gstatic = 'https://fonts.gstatic.com/s/inter/v13/font.woff2';
    $this->assertSame($gstatic, UiStyleSettings::stylesheetUrl($gstatic));
  }

  public function test_stylesheet_url_accepts_same_origin_paths()
  {
    $this->assertSame('/storage/fonts/myfont.css', UiStyleSettings::stylesheetUrl('/storage/fonts/myfont.css'));
    $this->assertSame('/storage/fonts/myfont.css?v=2', UiStyleSettings::stylesheetUrl('/storage/fonts/myfont.css?v=2'));
    $this->assertSame('', UiStyleSettings::stylesheetUrl('/fonts/My Font.css'));
  }

  public function test_stylesheet_url_rejects_untrusted_sources()
  {
    $this->assertSame('', UiStyleSettings::stylesheetUrl(''));
    $this->assertSame('', UiStyleSettings::stylesheetUrl(null));
    $this->assertSame('', UiStyleSettings::stylesheetUrl('https://evil.test/font.css'));
    $this->assertSame('', UiStyleSettings::stylesheetUrl('http://fonts.googleapis.com/css2?family=Inter'));
    $this->assertSame('', UiStyleSettings::stylesheetUrl('//fonts.googleapis.com/css2?family=Inter'));
    $this->assertSame('', UiStyleSettings::stylesheetUrl('javascript:alert(1)'));
    $this->assertSame('', UiStyleSettings::stylesheetUrl('https://fonts.googleapis.com.evil.test/x.css'));
  }

  public function test_background_image_accepts_uploaded_filenames()
  {
    $this->assertSame('bastion-mobile.jpg', UiStyleSettings::backgroundImage('bastion-mobile.jpg'));
    $this->assertSame('bastion mobile-bg_2.webp', UiStyleSettings::backgroundImage(' bastion mobile-bg_2.webp '));
    $this->assertSame('loop.mp4', UiStyleSettings::backgroundImage('loop.mp4'));
  }

  public function test_background_image_rejects_paths_and_other_files()
  {
    $rejected = [
      '',
      '../../app.key',
      '../backgrounds/x.jpg',
      '/etc/passwd.jpg',
      'C:\\temp\\x.jpg',
      'frontend.js',
      'shell.php.jpg.txt',
      'x.jpg?cache=1',
      'x.php',
      'noextension',
    ];

    foreach ($rejected as $candidate) {
      $this->assertSame('', UiStyleSettings::backgroundImage($candidate));
    }
  }

  public function test_css_color_accepts_hex_and_function_colours()
  {
    $this->assertSame('#0f101e', UiStyleSettings::cssColor('#0f101e'));
    $this->assertSame('#FFF', UiStyleSettings::cssColor('#FFF'));
    $this->assertSame('#0f101e80', UiStyleSettings::cssColor('#0f101e80'));
    $this->assertSame('rgb(12, 34, 56)', UiStyleSettings::cssColor('rgb(12, 34, 56)'));
    $this->assertSame('rgba(12, 34, 56, 0.5)', UiStyleSettings::cssColor('rgba(12, 34, 56, 0.5)'));
    $this->assertSame('hsl(210 40% 20%)', UiStyleSettings::cssColor('hsl(210 40% 20%)'));
  }

  public function test_css_color_rejects_anything_else()
  {
    $this->assertSame('', UiStyleSettings::cssColor('red; } body { display: none'));
    $this->assertSame('', UiStyleSettings::cssColor('url(//evil.test/x.png)'));
    $this->assertSame('', UiStyleSettings::cssColor('javascript:alert(1)'));
    $this->assertSame('', UiStyleSettings::cssColor(''));
    $this->assertSame('#000000', UiStyleSettings::cssColor(null, '#000000'));
  }

  public function test_gradient_angle_is_clamped_to_degrees()
  {
    $this->assertSame(180, UiStyleSettings::gradientAngle('180'));
    $this->assertSame(0, UiStyleSettings::gradientAngle('0'));
    $this->assertSame(360, UiStyleSettings::gradientAngle('360'));
    $this->assertSame(180, UiStyleSettings::gradientAngle('361'));
    $this->assertSame(180, UiStyleSettings::gradientAngle('-90'));
    $this->assertSame(180, UiStyleSettings::gradientAngle('180; color: red'));
    $this->assertSame(90, UiStyleSettings::gradientAngle(null, 90));
  }

  public function test_mobile_background_defaults_to_desktop()
  {
    $this->assertSame(
      ['active' => false, 'image' => '', 'color' => ''],
      UiStyleSettings::mobileBackground([])
    );

    $this->assertSame(
      ['active' => false, 'image' => '', 'color' => ''],
      UiStyleSettings::mobileBackground(['mobile_background_type' => 'desktop'])
    );

    $this->assertSame(
      ['active' => false, 'image' => '', 'color' => ''],
      UiStyleSettings::mobileBackground(['mobile_background_type' => 'nonsense'])
    );
  }

  public function test_mobile_background_image_requires_an_existing_upload()
  {
    $settings = [
      'mobile_background_type' => 'image',
      'mobile_background_image' => 'mobile-background.webp',
    ];

    $this->assertSame(
      ['active' => true, 'image' => 'url(/images/mobile-background.webp)', 'color' => ''],
      UiStyleSettings::mobileBackground($settings, fn ($file) => $file === 'mobile-background.webp')
    );

    // deleted upload -> falls back to the desktop background instead of a blank screen
    $this->assertSame(
      ['active' => false, 'image' => '', 'color' => ''],
      UiStyleSettings::mobileBackground($settings, fn () => false)
    );
  }

  public function test_mobile_background_image_rejects_paths()
  {
    $this->assertSame(
      ['active' => false, 'image' => '', 'color' => ''],
      UiStyleSettings::mobileBackground([
        'mobile_background_type' => 'image',
        'mobile_background_image' => '../../app.key',
      ])
    );
  }

  public function test_mobile_background_can_be_a_gradient()
  {
    $this->assertSame(
      [
        'active' => true,
        'image' => 'linear-gradient(200deg, #0f101e, #00a8cc)',
        'color' => '',
      ],
      UiStyleSettings::mobileBackground([
        'mobile_background_type' => 'gradient',
        'mobile_background_gradient_from' => '#0f101e',
        'mobile_background_gradient_to' => '#00a8cc',
        'mobile_background_gradient_angle' => '200',
      ])
    );
  }

  public function test_mobile_background_gradient_with_invalid_colour_is_inactive()
  {
    $this->assertSame(
      ['active' => false, 'image' => '', 'color' => ''],
      UiStyleSettings::mobileBackground([
        'mobile_background_type' => 'gradient',
        'mobile_background_gradient_from' => '#0f101e',
        'mobile_background_gradient_to' => 'red; } body { display: none',
      ])
    );
  }

  public function test_mobile_background_can_be_a_solid_colour()
  {
    $this->assertSame(
      ['active' => true, 'image' => '', 'color' => '#0f101e'],
      UiStyleSettings::mobileBackground([
        'mobile_background_type' => 'color',
        'mobile_background_color' => '#0f101e',
      ])
    );

    $this->assertSame(
      ['active' => false, 'image' => '', 'color' => ''],
      UiStyleSettings::mobileBackground([
        'mobile_background_type' => 'color',
        'mobile_background_color' => 'not-a-colour',
      ])
    );
  }
}
