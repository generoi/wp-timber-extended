<?php
/*
Plugin Name:        Timber Extended
Plugin URI:         http://genero.fi
Description:        Replace Wordpress templating system with timber and extend it further.
Version:            0.0.1
Author:             Genero
Author URI:         http://genero.fi/

License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
  exit;
}

if (!class_exists('Timber')) {
  add_action('admin_notices', function() {
    echo 'Timber not activated. Make sure you activate the plugin in <a href="/wp-admin/plugins.php#timber">/wp-admin/plugins.php</a>';
  });
  exit;
}

class TimberExtended {
  private static $instances = array();

  private $features = array();

  public static function get_instance() {
    $cls = get_called_class();
    if (!isset(self::$instances[$cls])) {
      self::$instances[$cls] = new static;
    }
    return self::$instances[$cls];
  }

  public function init() {
    $includes_dir = __DIR__ . '/includes';
    if (apply_filters('timber-extended/timber-widget', true)) {
      require_once $includes_dir . '/class-wp-timber-extended-timber-widget.php';
    }
    if (apply_filters('timber-extended/timber-menu', true)) {
      require_once $includes_dir . '/class-wp-timber-extended-timber-menu.php';
      require_once $includes_dir . '/class-wp-timber-extended-timber-menu-item.php';
    }
    if (apply_filters('timber-extended/timber-language-menu', true)) {
      require_once $includes_dir . '/class-wp-timber-extended-timber-language-menu.php';
    }

    add_action('after_setup_theme', [$this, 'loadModules'], 99);
    add_action('debug_bar_panels', [$this, 'addDebugBar']);
  }

  public function loadModules() {
    foreach (glob(__DIR__ . '/modules/*.php') as $file) {
      $feature = 'timber-extended-' . basename($file, '.php');
      if (get_theme_support($feature)) {
        require_once $file;
      }
    }
  }

  public function addDebugBar($panels) {
    require_once __DIR__ . '/includes/class-wp-timber-extended-debug-bar.php';
    $panels[] = new Debug_Bar_TimberExtended();
    return $panels;
  }

  public function hasThemeFeature($feature) {
    return in_array($feature, $this->features);
  }

  public function setThemeFeatures($features) {
    $this->features = $features;
  }

  public static function get_page_type() {
    if     (is_embed())             $type = 'embed';
    elseif (is_404())               $type = '404';
    elseif (is_search())            $type = 'search';
    elseif (is_front_page())        $type = 'front_page';
    elseif (is_home())              $type = 'home';
    elseif (is_post_type_archive()) $type = 'post_type_archive';
    elseif (is_tax())               $type = 'tax';
    elseif (is_attachment())        $type = 'attachment';
    elseif (is_single())            $type = 'single';
    elseif (is_page())              $type = 'page';
    elseif (is_singular())          $type = 'singular';
    elseif (is_category())          $type = 'category';
    elseif (is_tag())               $type = 'tag';
    elseif (is_author())            $type = 'author';
    elseif (is_date())              $type = 'date';
    elseif (is_archive())           $type = 'archive';

    return apply_filters('timber_extended/templates/page_type', $type);
  }

}

TimberExtended::get_instance()->init();
