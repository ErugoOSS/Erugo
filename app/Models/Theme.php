<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
  protected $fillable = ['name', 'theme', 'active', 'category'];

  protected $casts = [
    'theme' => 'object',
  ];

  public function getThemeAttribute($value)
  {
    
    // Make sure we're working with a proper object
    $rawTheme = $this->attributes['theme'] ?? '{}';

    // Handle case where theme might be stored as a JSON string
    if (is_string($rawTheme)) {
      $theme = json_decode($rawTheme);
      if (json_last_error() !== JSON_ERROR_NONE) {
        // If JSON is invalid, return a default theme
        return $this->getDefaultTheme();
      }
    } else {
      // If it's already an object, just use it
      $theme = json_decode(json_encode($rawTheme));
    }

    // Helper function to sanitize CSS values
    $sanitizeCssValue = function ($value) {
      if (!is_string($value)) {
        return $value;
      }

      // Remove potentially dangerous patterns
      $dangerous = [
        // JavaScript protocols
        '/javascript:/i',
        '/data:/i',
        // Function calls that could execute JS
        '/expression\s*\(/i',
        '/eval\s*\(/i',
        '/alert\s*\(/i',
        '/confirm\s*\(/i',
        '/prompt\s*\(/i',
        '/document\./i',
        '/window\./i',
        // CSS imports
        '/@import/i',
        // HTML tags
        '/<\/?[a-z][^>]*>/i',
        // Script injection
        '/<script>|<\/script>/i',
        // Event handlers
        '/on\w+\s*=/i',
        // Binding exploits
        '/-moz-binding/i',
        '/behavior\s*:/i',
        // Comment endings that could break out of comments
        '/\*\//i',
        // SQL injection patterns
        '/;\s*DROP\s+TABLE/i',
        // Various obfuscation techniques
        '/eval\s*\(/i',
        '/atob\s*\(/i',
        '/fetch\s*\(/i'
      ];

      foreach ($dangerous as $pattern) {
        $value = preg_replace($pattern, '[removed]', $value);
      }

      // Absolute stylesheet URLs (used by fonts -> importUrl). Handled before the
      // generic filter below, which would otherwise strip the query string.
      if (preg_match('#^https?://#i', trim($value))) {
        return $this->sanitizeRemoteUrl($value);
      }

      // Protocol-relative URLs are rejected outright: a font stylesheet must be
      // explicit https or a same-origin path.
      if (str_starts_with(trim($value), '//')) {
        return '[url-removed]';
      }

      // Only allow specific patterns for gradients and colors
      if (preg_match('/^(#[0-9a-f]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|[a-z-]+|linear-gradient\(([^()]|(\([^()]*\)))*\))$/i', $value)) {
        return $value;
      }

      // Only allow safe dimensions
      if (preg_match('/^[0-9]+(\.[0-9]+)?(%|px|rem|em|vh|vw|vmin|vmax)$/i', $value)) {
        return $value;
      }

      // Only allow safe URL references if they reference data schemes or known safe domains
      if (preg_match('/url\s*\(([^)]+)\)/i', $value, $matches)) {
        $url = trim($matches[1], '\'"');
        // Only allow relative URLs or URLs to trusted domains
        if (strpos($url, '/') === 0 || strpos($url, './') === 0) {
          return "url('{$url}')";
        }
        return '[url-removed]';
      }

      // Only allow a subset of CSS functions
      $safeFunctions = [
        'calc',
        'min',
        'max',
        'clamp',
        'var'
      ];

      foreach ($safeFunctions as $func) {
        if (preg_match('/^' . $func . '\s*\(([^()]|(\([^()]*\)))*\)$/i', $value)) {
          // Further sanitize the content inside these functions
          $sanitizedValue = preg_replace('/[^\w\s\-\.\,\(\)\#\%\/\:rgb\;]/i', '', $value);
          return $sanitizedValue;
        }
      }

      // For anything else, strictly filter to basic CSS characters
      return preg_replace('/[^\w\s\-\.\,\(\)\#\%\/\:rgb\;]/i', '', $value);
    };

    // Recursive function to sanitize all values in the theme object
    $sanitizeThemeObject = function (&$obj) use (&$sanitizeThemeObject, $sanitizeCssValue) {
      if (!is_object($obj) && !is_array($obj)) {
        return $sanitizeCssValue($obj);
      }

      foreach ($obj as $key => &$value) {
        if (is_object($value) || is_array($value)) {
          $sanitizeThemeObject($value);
        } else {
          $value = $sanitizeCssValue($value);
        }
      }
      return $obj;
    };

    // Sanitize the entire theme object
    $sanitizeThemeObject($theme);

    // Background scaling: strict keyword allowlist so a theme cannot inject
    // arbitrary declarations into the background shorthand.
    if (isset($theme->background) && is_object($theme->background)) {
      $theme->background = $this->sanitizeBackgroundNode($theme->background);

      if (isset($theme->background->mobile) && is_object($theme->background->mobile)) {
        $theme->background->mobile = $this->sanitizeBackgroundNode($theme->background->mobile);
      }
    }

    // Fonts: family names are restricted to plain font stacks
    if (isset($theme->fonts) && is_object($theme->fonts)) {
      foreach (['body', 'heading'] as $fontKey) {
        if (isset($theme->fonts->{$fontKey})) {
          $theme->fonts->{$fontKey} = $this->sanitizeFontFamily($theme->fonts->{$fontKey});
        }
      }
    }

    // Validate mandatory structure to prevent missing elements
    if (
      !isset($theme->links) || !isset($theme->buttons) ||
      !isset($theme->buttons->primary) || !isset($theme->buttons->secondary)
    ) {

      // Merge with defaults to fill missing parts
      $theme = (object) array_merge((array) $this->getDefaultTheme(), (array) $theme);
    }

    return $theme;
  }

  /**
   * Sanitize a background scaling node (imageSize / imagePosition),
   * either the top level background block or its mobile override.
   */
  private function sanitizeBackgroundNode($node)
  {
    if (isset($node->imageSize)) {
      $node->imageSize = $this->sanitizeBackgroundSize($node->imageSize);
    }

    if (isset($node->imagePosition)) {
      $node->imagePosition = $this->sanitizeBackgroundPosition($node->imagePosition);
    }

    return $node;
  }

  /**
   * background-size values: keywords or one/two lengths, e.g.
   * "cover", "contain", "100% auto", "auto 100%"
   */
  private function sanitizeBackgroundSize($value, string $fallback = 'cover'): string
  {
    $tokens = preg_split('/\s+/', strtolower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY);

    if (empty($tokens) || count($tokens) > 2) {
      return $fallback;
    }

    foreach ($tokens as $token) {
      if (!preg_match('/^(auto|cover|contain|[0-9]+(\.[0-9]+)?(%|px|rem|em|vh|vw|vmin|vmax))$/', $token)) {
        return $fallback;
      }
    }

    return implode(' ', $tokens);
  }

  /**
   * background-position values, e.g. "center center", "center top", "50% 25%"
   */
  private function sanitizeBackgroundPosition($value, string $fallback = 'center center'): string
  {
    $tokens = preg_split('/\s+/', strtolower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY);

    if (empty($tokens) || count($tokens) > 4) {
      return $fallback;
    }

    foreach ($tokens as $token) {
      if (!preg_match('/^(auto|center|left|right|top|bottom|[0-9]+(\.[0-9]+)?(%|px|rem|em|vh|vw))$/', $token)) {
        return $fallback;
      }
    }

    return implode(' ', $tokens);
  }

  /**
   * font-family values: plain font stacks only (letters, digits, spaces,
   * commas and hyphens). Quotes are stripped by the generic value filter.
   */
  private function sanitizeFontFamily($value, string $fallback = 'Outfit, sans-serif'): string
  {
    $value = trim(preg_replace('/\s+/', ' ', (string) $value));

    return preg_match('/^[A-Za-z0-9][A-Za-z0-9 ,\-]*$/', $value) ? $value : $fallback;
  }

  /**
   * Allow https URLs on trusted font hosts only, or a same-origin path.
   * Used for the optional font stylesheet (fonts.importUrl).
   */
  private function sanitizeRemoteUrl($value): string
  {
    $value = trim((string) $value);

    // Same-origin path, e.g. /fonts/myfont.css. Protocol-relative URLs are rejected.
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
      return preg_match('#^/[A-Za-z0-9\-._~/%]+$#', $value) ? $value : '[url-removed]';
    }

    $parts = parse_url($value);

    if (
      $parts === false ||
      !isset($parts['scheme'], $parts['host']) ||
      strtolower($parts['scheme']) !== 'https'
    ) {
      return '[url-removed]';
    }

    $allowedHosts = ['fonts.googleapis.com', 'fonts.gstatic.com'];

    if (!in_array(strtolower($parts['host']), $allowedHosts, true)) {
      return '[url-removed]';
    }

    return preg_replace('/["\'<>()\s;\\\\]/', '', $value);
  }

  /**
   * Get default theme structure when theme is invalid or missing parts
   */
  private function getDefaultTheme()
  {
    return (object) [
      'links' => (object) [
        'default' => 'rgb(187, 134, 252)',
        'hover' => 'rgb(203, 166, 247)',
        'active' => 'rgb(221, 195, 255)',
        'disabled' => 'rgba(190, 190, 190, 0.4)'
      ],
      'background' => (object) [
        'imageSize' => 'cover',
        'imagePosition' => 'center center',
        'mobile' => (object) [
          'imageSize' => '100% auto',
          'imagePosition' => 'center top'
        ]
      ],
      'fonts' => (object) [
        'body' => 'Outfit, sans-serif',
        'heading' => 'Outfit, sans-serif',
        'importUrl' => ''
      ],
      'buttons' => (object) [
        'primary' => (object) [
          'default' => (object) [
            'background' => 'linear-gradient(135deg, rgb(137, 87, 229) 0%, rgb(156, 113, 232) 100%)',
            'text' => 'rgb(255, 255, 255)',
            'boxShadow' => '0 2px 8px rgba(137, 87, 229, 0.3)'
          ],
          'hover' => (object) [
            'background' => 'linear-gradient(135deg, rgb(156, 113, 232) 0%, rgb(174, 137, 238) 100%)',
            'text' => 'rgb(255, 255, 255)',
            'boxShadow' => '0 3px 10px rgba(137, 87, 229, 0.4)'
          ],
          'active' => (object) [
            'background' => 'linear-gradient(135deg, rgb(174, 137, 238) 0%, rgb(187, 154, 242) 100%)',
            'text' => 'rgb(255, 255, 255)',
            'boxShadow' => '0 2px 6px rgba(137, 87, 229, 0.3)'
          ],
          'disabled' => (object) [
            'background' => 'linear-gradient(135deg, rgba(137, 87, 229, 0.4) 0%, rgba(156, 113, 232, 0.4) 100%)',
            'text' => 'rgba(255, 255, 255, 0.4)',
            'boxShadow' => 'none'
          ]
        ],
        'secondary' => (object) [
          'default' => (object) [
            'background' => 'linear-gradient(135deg, rgb(44, 44, 52) 0%, rgb(50, 50, 60) 100%)',
            'text' => 'rgb(220, 220, 220)',
            'boxShadow' => '0 2px 6px rgba(0, 0, 0, 0.2)'
          ],
          'hover' => (object) [
            'background' => 'linear-gradient(135deg, rgb(56, 56, 66) 0%, rgb(62, 62, 74) 100%)',
            'text' => 'rgb(230, 230, 230)',
            'boxShadow' => '0 3px 8px rgba(0, 0, 0, 0.25)'
          ],
          'active' => (object) [
            'background' => 'linear-gradient(135deg, rgb(66, 66, 78) 0%, rgb(72, 72, 86) 100%)',
            'text' => 'rgb(240, 240, 240)',
            'boxShadow' => '0 2px 4px rgba(0, 0, 0, 0.2)'
          ],
          'disabled' => (object) [
            'background' => 'linear-gradient(135deg, rgba(50, 50, 50, 0.5) 0%, rgba(60, 60, 60, 0.5) 100%)',
            'text' => 'rgba(200, 200, 200, 0.4)',
            'boxShadow' => 'none'
          ]
        ]
      ]
    ];
  }
}
