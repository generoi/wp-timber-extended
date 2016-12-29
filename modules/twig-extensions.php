<?php

namespace TimberExtended;

use Timber;
use TimberHelper;
use Twig_SimpleFilter;
use Twig_SimpleFunction;
use Twig_Extension_StringLoader;

class TwigExtensions extends \TimberExtended {

  public function init() {
    $features = get_theme_support('timber-extended-twig-extensions');
    if (!empty($features[0])) {
      $this->setThemeFeatures($features[0]);
    }

    if ($this->hasThemeFeature('core')) {
      add_filter('get_twig', [$this, 'add_core']);
    }
    if ($this->hasThemeFeature('contrib')) {
      add_filter('get_twig', [$this, 'add_contrib']);
    }
    if ($this->hasThemeFeature('functional')) {
      add_filter('get_twig', [$this, 'add_functional']);
    }
  }

  public function add_core($twig) {
    $twig->addExtension(new Twig_Extension_StringLoader());

    // Filter a list of posts (or one) by having a term.
    // @todo inefficient.
    // Usage: {% if post|has_term('project-2') %}
    $twig->addFilter('has_term', new Twig_SimpleFilter('has_term', [$this, 'filter_has_term']));

    // Get posts.
    // Usage: {% set posts = get_posts({'post_type': 'page'}) %}
    // Note: If you're using the pager functionality, you need to run get_pager()
    // for the original WP_Query to be restored.
    $twig->addFunction('get_posts', new Twig_SimpleFunction('get_posts', [$this, 'fn_get_posts']));

    // Get pager.
    // Usage: {% set pager = get_pager() %}
    $twig->addFunction('get_pager', new Twig_SimpleFunction('get_pager', [$this, 'fn_get_pager']));

    // Get terms.
    // Usage: {% set posts = get_terms('category_name', {'parent': 0}) %}
    $twig->addFunction('get_terms', new Twig_SimpleFunction('get_terms', [$this, 'fn_get_terms']));

    // Get the dimensions of a defined image size.
    // Usage:
    // {% set dimensions = get_image_size('teaser') %}
    // <img src="{{thumbnail|resize(dimensions.width, dimensions.height, dimensions.crop)}}">
    // @see https://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
    $twig->addFunction('get_image_size', new Twig_SimpleFunction('get_image_size', [$this, 'fn_get_image_size']));

    // Get terms.
    // Usage: {{ section('text', 'foo', ob_function('woocommerce_template_single_price'), 'dark-blue') }}
    $twig->addFunction('ob_function', new Twig_SimpleFunction('ob_function', [$this, 'fn_ob_function']));

    // Pass a string through the_content filters.
    // Usage: {{ post.foobar|the_content }}
    $twig->addFilter('the_content', new Twig_SimpleFilter('the_content', [$this, 'filter_the_content']));

    return $twig;
  }

  public function add_contrib($twig) {
    // Usage: {{ facetwp_display('facet', 'house_type') }}
    $twig->addFunction('facetwp_display', new Twig_SimpleFunction('facetwp_display', [$this, 'fn_facetwp_display']));

    // Call kint debugger
    // Usage: {{ d(post) }}
    $twig->addFunction('d', new Twig_SimpleFunction('d', [$this, 'fn_d']));

    // Call kint debugger
    // Usage: {{ post|d }}
    $twig->addFilter('d', new Twig_SimpleFilter('d', [$this, 'fn_d']));

    // Return a WPML translated object permalink.
    // Usage: {{ wpml_url('contact', 'page') }}
    $twig->addFunction('wpml_url', new Twig_SimpleFunction('wpml_url', [$this, 'fn_wpml_url']));

    return $twig;
  }

  public function add_functional($twig) {
    // Filter a list of objects or arrays.
    // Usage: {{ posts|filter('post_type', 'product') }}
    $twig->addFilter('filter', new Twig_SimpleFilter('filter', [$this, 'filter_filter']));

    // Apply a function to all items in a list.
    // Usage: {{ post.organizers|map('intval') }}
    $twig->addFilter('map', new Twig_SimpleFilter('map', [$this, 'filter_map']));

    // Pluck a property from a list of objects or arrays.
    // Usage: {{ posts|pluck('post_title') }}
    $twig->addFilter('pluck', new Twig_SimpleFilter('pluck', [$this, 'filter_pluck']));

    // Pluck a property from a list of objects or arrays.
    // Usage: {{ block_grid|values|join(' ') }}
    $twig->addFilter('values', new Twig_SimpleFilter('values', [$this, 'filter_values']));

    // Pluck a property from a list of objects or arrays.
    // Usage: {{ block_grid|keys|join(' ') }}
    $twig->addFilter('keys', new Twig_SimpleFilter('keys', [$this, 'filter_keys']));

    // Group a set of posts by a term.
    // @todo inefficient.
    // Usage: {{ posts|group_by_term('category') }}
    $twig->addFilter('group_by_term', new Twig_SimpleFilter('group_by_term', [$this, 'filter_group_by_term']));

    return $twig;
  }

  // Core

  public function fn_get_posts($options = NULL) {
    $options = $this->toArray($options);
    if (is_string($options)) {
      $post_type = $options;
      $options = array();
      $options['post_type'] = $post_type;
    }
    if (!empty($options['paged'])) {
      global $paged;
      if (!isset($paged) || !$paged){
        $paged = 1;
      }
      $options['paged'] = $paged;
      query_posts($options);
      $posts = Timber::get_posts($options);
      // wp_reset_query() is called in get_pager().
      return $posts;
    }
    return Timber::get_posts($options);
  }

  public function fn_get_pager($options = array()) {
    $pager = Timber::get_pagination($this->toArray($options));
    wp_reset_query();
    return $pager;
  }

  public function fn_get_terms($category, $options = NULL) {
    return Timber::get_terms($category, $this->toArray($options));
  }

  public function fn_get_image_size($size) {
    global $_wp_additional_image_sizes;
    $sizes = [];
    foreach (get_intermediate_image_sizes() as $_size) {
      if (in_array($_size, array('thumbnail', 'medium', 'medium_large', 'large'))) {
        $sizes[$_size]['width']  = get_option("{$_size}_size_w");
        $sizes[$_size]['height'] = get_option("{$_size}_size_h");
        $sizes[$_size]['crop']   = (bool) get_option("{$_size}_crop");
      } elseif (isset($_wp_additional_image_sizes[$_size])) {
        $sizes[$_size] = [
          'width'  => $_wp_additional_image_sizes[$_size]['width'],
          'height' => $_wp_additional_image_sizes[$_size]['height'],
          'crop'   => $_wp_additional_image_sizes[$_size]['crop'],
        ];
      }
    }
    if (isset($sizes[$size])) {
      return $sizes[$size];
    }
    return false;
  }

  public function fn_ob_function($fn, ...$args) {
    return TimberHelper::ob_function($fn, $args);
  }

  public function filter_the_content($content = '') {
    return apply_filters('the_content', $content);
  }

  public function filter_has_term($array, $term, $category = '') {
    if (is_object($array)) {
      return has_term($term, $category, $array);
    }
    // @todo optimize.
    return array_filter($array, function ($item) use ($term, $category) {
      return has_term($term, $category, $item);
    });
  }

  // Functional

  public function filter_group_by_term($array, $category) {
    $groups = array();
    foreach ($array as $item) {
      $terms = get_the_terms($item, $category);
      if (!$terms) {
        continue;
      }
      foreach ($terms as $term) {
        if (!isset($groups[$term->term_id])) {
          $groups[$term->term_id] = new \stdClass();
          $groups[$term->term_id]->term = new Timber\Term($term);
          $groups[$term->term_id]->posts = array();
        }
        $groups[$term->term_id]->posts[] = $item;
      }
    }
    return $groups;
  }

  public function filter_pluck($array, $key) {
    $return = array();
    foreach ($array as $item) {
      if (is_object($item) && isset($item->$key)) {
        $return[] = $item->$key;
      }
      elseif (is_array($item) && isset($item[$key])) {
        $return[] = $item[$key];
      }
    }
    return $return;
  }

  public function filter_keys($array) {
    if (is_array($array)) {
      return array_keys($array);
    }
    return [];
  }

  public function filter_values($array) {
    if (is_array($array)) {
      return array_values($array);
    }
    return [];
  }

  public function filter_map($array, $function, ...$args) {
    if (!is_array($array)) {
      $array = [$array];
    }
    return array_map($function, $array, $args);
  }

  public function filter_filter($array, $key, $value = NULL) {
    if (!is_array($array)) {
      return [];
    }
    return array_filter($array, function ($item) use ($key, $value) {
      // Filter by value.
      if (isset($value)) {
        if (is_object($item)) {
          return $item->$key === $value;
        }
        elseif (is_array($item)) {
          return $item[$key] === $value;
        }
        return FALSE;
      }
      // Filter by key existance.
      else {
        if (is_object($item)) {
          return isset($item->$key);
        }
        elseif (is_array($item)) {
          // Sequential array.
          if (array_values($item) === $item) {
            return in_array($key, $item);
          }
          // Associative array.
          else {
            return isset($item[$key]);
          }
        }
        return $item === $key;
      }
    });
  }

  // Contrib

  public function fn_facetwp_display(...$args) {
    return facetwp_display(...$args);
  }

  public function fn_d(...$args) {
    return d(...$args);
  }

  public function fn_wpml_url($id, $type = 'page', $return = false, $langcode = null) {
    if (is_string($id) && !is_numeric($id)) {
      switch ($type) {
        case 'page':
          $id = get_page_by_path($id);
          break;
      }
    }
    $id = apply_filters('wpml_object_id', $id, $type, $return, $langcode);
    return get_permalink($id);
  }

  // Utils

  private function toArray($options = NULL) {
    if (isset($options) && is_object($options)) {
      $options = json_decode(json_encode($options), true);
    }
    return $options;
  }

}

TwigExtensions::get_instance()->init();
