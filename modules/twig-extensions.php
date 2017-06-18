<?php

namespace TimberExtended;

use Timber;
use TimberHelper;
use Twig_SimpleFilter;
use Twig_SimpleFunction;
use Twig_Extension_StringLoader;
use WP_Query;

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
    $twig->addFunction('get_posts', new Twig_SimpleFunction('get_posts', [$this, 'fn_get_posts']));

    // Get the paged number.
    $twig->addFunction('get_paged', new Twig_SimpleFunction('get_paged', [$this, 'fn_get_paged']));

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

    // Strip all leading and trailing whitespace, newlines as well as html entity codes.
    // Usage: {{post.get_preview()|wptrim}}
    $twig->addFilter('wptrim', new Twig_SimpleFilter('wptrim', [$this, 'filter_wptrim']));

    $twig->addFunction('post', new Twig_SimpleFunction('post', [$this, 'fn_post']));

    // Polylang integration
    $twig->addFunction('pll__', new Twig_SimpleFunction('pll__', function ($string) {
        return pll__($string);
    }));
    $twig->addFunction('pll_e', new Twig_SimpleFunction('pll_e', function ($string) {
        return pll_e($string);
    }));

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

    // Merge that works with objects.
    // Usage: {{ widget|merge(widget.section|default({})) }}
    $twig->addFilter('obj_merge', new Twig_SimpleFilter('obj_merge', [$this, 'filter_obj_merge']));

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

    // Value filters.
    $twig->addFilter('bool', new Twig_SimpleFilter('bool', 'boolval'));
    $twig->addFilter('int', new Twig_SimpleFilter('int', 'intval'));
    return $twig;
  }

  // Core

  public function fn_get_posts($options = NULL) {
    if (!($options instanceof WP_Query)) {
      $options = $this->toArray($options);
      if (is_string($options)) {
        $post_type = $options;
        $options = array();
        $options['post_type'] = $post_type;
      }
    }
    return new Timber\PostQuery($options);
  }

  public function fn_get_paged() {
    return get_query_var('paged') ? get_query_var('paged') : 1;
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

  public function filter_wptrim($content = '') {
    // @see https://stackoverflow.com/a/22004695/319855
    $content = preg_replace('#^(<br\s*/?>|\s|&nbsp;)*(.+?)(<br\s*/?>|\s|&nbsp;)*$#i', '$2', $content);
    return trim($content);
  }

  public function fn_post($pid) {
    return Timber\PostGetter::get_post($pid);
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
    $groups = [];
    // Iterate over posts.
    foreach ($array as $item) {
      // Get all post terms in specified category.
      // @todo optimize.
      $terms = get_the_terms($item, $category);
      if (!$terms) {
        continue;
      }

      // Group posts by term.
      foreach ($terms as $term) {
        if (!isset($groups[$term->term_id])) {
          $groups[$term->term_id] = new \stdClass();
          $groups[$term->term_id]->term = new Timber\Term($term);
          $groups[$term->term_id]->posts = array();
          $groups[$term->term_id]->children = array();
          $groups[$term->term_id]->parents = array();
        }
        $groups[$term->term_id]->posts[] = $item;
      }

      // Iterate over the groups and attach children and parents ids.
      foreach ($groups as $term_id => $group) {
        $parent_id = $group->term->parent;

        if ($parent_id != 0) {
          $parent_id = (string) $parent_id;

          if (isset($groups[$parent_id])) {
            // Attach each child term to the parent.
            $groups[$parent_id]->children[] = $term_id;

            if (!in_array($parent_id, $group->parents)) {
              $group->parents[] = $parent_id;

              // If there's a grandparent attach that them too.
              if (!empty($groups[$parent_id])) {
                $grandparent_id = $groups[$parent_id]->term->parent;

                if ($grandparent_id && !in_array($grandparent_id, $group->parents)) {
                  $group->parents[] = $grandparent_id;
                }
              }
            }
          }
        }
      }
    }

    uasort($groups, function ($a, $b) {
      $a_order = isset($a->term->term_order) ? $a->term->term_order : 100;
      $b_order = isset($b->term->term_order) ? $b->term->term_order : 100;

      if ($a_order == $b_order) {
        return 0;
      }
      return ($a_order < $b_order) ? -1 : 1;
    });

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

  public function filter_obj_merge($array, $value) {
    if (is_object($value)) {
      $value = (array) $value;
    }

    if (is_object($array)) {
      foreach ($value as $key => $val) {
        $array->$key = $val;
      }
    }
    elseif (is_array($array)) {
      $array = array_merge($array, $value);
    }

    return $array;
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
