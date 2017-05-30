<?php

namespace TimberExtended;

use Timber;

class TimberBasics extends \TimberExtended {

  public function init() {
    add_filter('timber/context', [$this, 'add_timber_context'], 9, 1);
    add_filter('timber/cache/location', [$this, 'timber_cache_location']);
  }

  public function timber_cache_location() {
    return WP_CONTENT_DIR . '/cache/timber';
  }

  public function add_timber_context($context) {
    $context['site']->theme_options = get_theme_mods();
    $context['site']->icon = new Timber\Image(get_site_icon_url());
    if ($logo_id = get_theme_mod('custom_logo')) {
      $context['site']->logo = new Timber\Image($logo_id);
    }
    // Timber doesn't support bedrock-like directory structures.
    $context['site']->siteurl = get_site_url();
    // Add Yoast social options if available.
    if (class_exists('WPSEO_Options')) {
      $context['site']->social = \WPSEO_Options::get_option('wpseo_social');
    }
    return $context;
  }
}

TimberBasics::get_instance()->init();
