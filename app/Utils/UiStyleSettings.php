<?php

namespace App\Utils;

/**
 * Validation helpers for the branding/style settings that are exposed as CSS
 * custom properties (background scaling, mobile background, logo placement
 * and fonts).
 *
 * These settings end up inside a <style> block in the page head, so every value
 * is checked against an explicit allowlist instead of being echoed as-is.
 * Keeping the rules here means the blade template, the API and the tests all
 * share one definition of what is a valid value.
 */
class UiStyleSettings
{
  /**
   * Selectable background-size values.
   */
  public const BACKGROUND_SIZES = [
    'cover',
    'contain',
    '100% auto',
    'auto 100%',
  ];

  /**
   * Selectable background-position values.
   */
  public const BACKGROUND_POSITIONS = [
    'center center',
    'center top',
    'center bottom',
    'top left',
    'top right',
    'bottom left',
    'bottom right',
    'left center',
    'right center',
  ];

  /**
   * Selectable logo alignments, mapped to justify-content.
   */
  public const LOGO_ALIGNMENTS = [
    'left' => 'flex-start',
    'center' => 'center',
    'right' => 'flex-end',
  ];

  /**
   * Ways a mobile background can be drawn.
   */
  public const MOBILE_BACKGROUND_TYPES = [
    'desktop',
    'image',
    'gradient',
    'color',
  ];

  /**
   * Valid background-size, e.g. "cover", "contain", "100% auto".
   */
  public static function backgroundSize($value, string $fallback = 'cover'): string
  {
    $normalized = self::normalizeInput($value);

    return in_array($normalized, self::BACKGROUND_SIZES, true) ? $normalized : $fallback;
  }

  /**
   * Valid background-position, e.g. "center center" or "center top".
   */
  public static function backgroundPosition($value, string $fallback = 'center center'): string
  {
    $normalized = self::normalizeInput($value);

    return in_array($normalized, self::BACKGROUND_POSITIONS, true) ? $normalized : $fallback;
  }

  /**
   * Logo alignment as a justify-content value.
   */
  public static function logoAlignment($value, string $fallback = 'flex-start'): string
  {
    $normalized = strtolower(trim((string) $value));

    return self::LOGO_ALIGNMENTS[$normalized] ?? $fallback;
  }

  /**
   * A font stack, normalized so every family is explicitly quoted.
   *
   * Quoting matters: an identifier cannot start with a digit, so a family such
   * as "Source Sans 3" is only valid CSS when quoted. Generic families are kept
   * unquoted so they still resolve to the generic font.
   *
   * @param string|null $value
   * @param string|null $fallback Null returns null, which lets callers fall
   *                              through to another font (e.g. heading -> body).
   */
  public static function fontFamily($value, ?string $fallback = '"Outfit", sans-serif'): ?string
  {
    $value = trim(preg_replace('/\s+/', ' ', (string) $value));

    if ($value === '') {
      return $fallback;
    }

    $genericFamilies = [
      'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui',
      'ui-serif', 'ui-sans-serif', 'ui-monospace', 'emoji', 'math', 'fangsong',
    ];

    $normalized = [];

    foreach (explode(',', $value) as $family) {
      $family = trim(trim($family), "\"'");

      if ($family === '') {
        continue;
      }

      // Plain family names only: letters, digits, spaces and hyphens.
      if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]*$/', $family)) {
        return $fallback;
      }

      $normalized[] = in_array(strtolower($family), $genericFamilies, true)
        ? strtolower($family)
        : '"' . $family . '"';
    }

    return $normalized === [] ? $fallback : implode(', ', $normalized);
  }

  /**
   * A stylesheet URL for loading a webfont: https on a trusted font host, or a
   * same-origin path. Returns an empty string when the value is not usable.
   */
  public static function stylesheetUrl($value): string
  {
    $value = trim((string) $value);

    if ($value === '') {
      return '';
    }

    if (preg_match('/javascript:|data:|expression\s*\(/i', $value)) {
      return '';
    }

    // Same-origin path, e.g. /storage/fonts/myfont.css (query string allowed so
    // uploads can be cache-busted). Protocol-relative URLs are rejected.
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
      return preg_match('@^/[A-Za-z0-9\-._~/%?=&#]*$@', $value) ? $value : '';
    }

    $parts = parse_url($value);

    if (
      $parts === false ||
      !isset($parts['scheme'], $parts['host']) ||
      strtolower($parts['scheme']) !== 'https'
    ) {
      return '';
    }

    if (!in_array(strtolower($parts['host']), ['fonts.googleapis.com', 'fonts.gstatic.com'], true)) {
      return '';
    }

    // Strip only characters that could break out of the attribute/CSS context.
    // Semicolons are kept: Google Fonts uses them for weight ranges (wght@400;700).
    return preg_replace('/["\'<>()\s\\\\]/', '', $value);
  }

  /**
   * A background image reference must be a bare filename of an uploaded file,
   * never a path, because it is joined onto a fixed storage folder.
   */
  public static function backgroundImage($value): string
  {
    $value = trim((string) $value);

    if ($value === '' || basename($value) !== $value || str_contains($value, '..')) {
      return '';
    }

    // Same characters the upload sanitiser allows, with a media extension.
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*\.(jpe?g|png|gif|webp|mp4|webm)$/i', $value)) {
      return '';
    }

    return $value;
  }

  /**
   * A CSS colour: #rgb, #rrggbb, #rrggbbaa, rgb(), rgba(), hsl() or hsla().
   * Returns the fallback (empty by default) for anything else.
   */
  public static function cssColor($value, string $fallback = ''): string
  {
    $value = trim((string) $value);

    if ($value === '') {
      return $fallback;
    }

    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
      return $value;
    }

    $functionColour = '/^(rgb|rgba|hsl|hsla)\(\s*[0-9.%,\s\/deg-]+\)$/i';

    return preg_match($functionColour, $value) ? $value : $fallback;
  }

  /**
   * A gradient angle in degrees, 0-360.
   */
  public static function gradientAngle($value, int $fallback = 180): int
  {
    $value = trim((string) $value);

    if (!preg_match('/^-?[0-9]{1,3}$/', $value)) {
      return $fallback;
    }

    $angle = (int) $value;

    return ($angle >= 0 && $angle <= 360) ? $angle : $fallback;
  }

  /**
   * Resolve the mobile background into CSS values.
   *
   * Returns ['active' => bool, 'image' => css background-image value,
   * 'color' => css background-color value]. When the type is an image the file
   * has to exist, which is checked through $fileExists so this stays testable
   * without touching the filesystem.
   *
   * @param array<string, mixed> $settings
   * @param callable(string): bool|null $fileExists
   * @return array{active: bool, image: string, color: string}
   */
  public static function mobileBackground(array $settings, ?callable $fileExists = null): array
  {
    $type = strtolower(trim((string) ($settings['mobile_background_type'] ?? 'desktop')));

    if (!in_array($type, self::MOBILE_BACKGROUND_TYPES, true) || $type === 'desktop') {
      return ['active' => false, 'image' => '', 'color' => ''];
    }

    if ($type === 'image') {
      $file = self::backgroundImage($settings['mobile_background_image'] ?? null);

      if ($file === '' || ($fileExists !== null && !$fileExists($file))) {
        return ['active' => false, 'image' => '', 'color' => ''];
      }

      return ['active' => true, 'image' => 'url(/images/' . $file . ')', 'color' => ''];
    }

    if ($type === 'gradient') {
      $from = self::cssColor($settings['mobile_background_gradient_from'] ?? null);
      $to = self::cssColor($settings['mobile_background_gradient_to'] ?? null);

      if ($from === '' || $to === '') {
        return ['active' => false, 'image' => '', 'color' => ''];
      }

      $angle = self::gradientAngle($settings['mobile_background_gradient_angle'] ?? null);

      return [
        'active' => true,
        'image' => sprintf('linear-gradient(%ddeg, %s, %s)', $angle, $from, $to),
        'color' => '',
      ];
    }

    $color = self::cssColor($settings['mobile_background_color'] ?? null);

    return $color === ''
      ? ['active' => false, 'image' => '', 'color' => '']
      : ['active' => true, 'image' => '', 'color' => $color];
  }

  private static function normalizeInput($value): string
  {
    return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
  }
}
