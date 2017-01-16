<?php

namespace TimberExtended;

use Timber;

class TimberBasics extends \TimberExtended {

  public function init() {
    add_filter('timber/context', [$this, 'add_timber_context'], 9, 1);
    add_filter('timber/cache/location', [$this, 'timber_cache_location']);
    if (isset($GLOBALS['sitepress']) && $GLOBALS['sitepress']->get_default_language() !== ICL_LANGUAGE_CODE) {
      add_filter('home_url', [$this, 'filter_sitepress_home_url'], 10, 4);
    }
  }

  public function timber_cache_location() {
    return WP_CONTENT_DIR . '/cache/timber';
  }

  public function add_timber_context($context) {
    $context['site']->logo = new Timber\Image(get_site_icon_url());
    // Timber doesn't support bedrock-like directory structures.
    $context['site']->siteurl = get_site_url();
    // Add Yoast social options if available.
    if (class_exists('WPSEO_Options')) {
      $context['site']->social = \WPSEO_Options::get_option('wpseo_social');
    }
    return $context;
  }

  /**
   * Fix when Timber uses WPML language-prefixed home_url() for relative image
   * paths.
   */
  public function filter_sitepress_home_url($url, $path, $scheme, $blog_id) {
    global $sitepress;
    if (!isset($sitepress) || $sitepress->get_default_language() == ICL_LANGUAGE_CODE) {
      return $url;
    }
    if (preg_match('/[\w\-]+\.(jpg|png|gif|jpeg)/', $path)) {
      // get_home_url() unfolded without apply_filters().
      global $pagenow;
      if (empty($blog_id) || !is_multisite()) {
        $home = get_option('home');
      } else {
        switch_to_blog($blog_id);
        $home = get_option('home');
        restore_current_blog();
      }

      if (!in_array($scheme, array('http', 'https', 'relative'))) {
        if (is_ssl() && !is_admin() && 'wp-login.php' !== $pagenow) {
          $scheme = 'https';
        } else {
          $scheme = parse_url($url, PHP_URL_SCHEME);
        }
      }
      $home = set_url_scheme($home, $scheme);
      return $home . '/' . ltrim($path, '/');
    }
    return $url;
  }
}

TimberBasics::get_instance()->init();
