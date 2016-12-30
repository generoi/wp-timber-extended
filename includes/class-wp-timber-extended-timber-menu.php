<?php

namespace TimberExtended;

use Timber;

/**
 * An extended Timber\Menu which adds BEM classes to menu items.
 *
 * @example
 * $context['menu'] = new TimberExtended\Menu('primary-menu');
 *
 * {% for item in menu.get_items %}
 *   <li class="{{item.class}}">
 *     <a href="{{item.get_link}}" class="{{item.link_class}}">{{item.title}}</a>
 *   </li>
 * {% endfor %}
 */
class Menu extends Timber\Menu
{
  public $MenuItemClass = 'TimberExtended\MenuItem';

  public $classes = array();
  public $class = '';

  // Eg. .nav-primary-menu
  public $classPrefix;

  public function __construct($slug = 0) {
    parent::__construct($slug);

    $this->classPrefix = apply_filters('timber-extended/menu-class-prefix', $this->slug);
    $this->add_class($this->classPrefix);
  }

  public function set_prefix($new) {
    $old = $this->classPrefix;
    $this->classes = array_diff($this->classes, [$old]);
    $this->class = implode(' ', $this->classes);
    $this->classPrefix = $new;
    $this->add_class($new);
    return $new;
  }

  public function add_class($class_name) {
    $this->classes[] = $class_name;
    $this->class .= ' ' . $class_name;
  }

  public function get_items() {
    $items = parent::get_items();
    foreach ($items as $item) {
      $this->add_item_classes($item, $this->classPrefix);
    }
    return $items;
  }

  public static function add_item_classes($item, $prefix) {
    $item->classPrefix = $prefix;

    $item->add_class($prefix . '__item');
    $item->add_link_class($prefix . '__link');

    if ($item->current || $item->current_item_ancestor || static::is_childpage($item->menu_object->object_id)) {
      $item->add_class($prefix . '__item--active');
      $item->add_link_class($prefix . '__link--active');
      $item->add_class('active');
    }
  }

  /**
   * Determine if a post is a descendant of the current page.
   */
  protected static function is_childpage($pid, $post = NULL) {
    if (is_null($post)) {
      $post = get_post();
    }
    if (!isset($post)) {
      return false;
    }
    if (is_page()) {
      if (!$post->post_parent) {
        return false;
      }
      if ($post->post_parent == $pid) {
        return true;
      }
      foreach (get_post_ancestors($post->ID) as $ancestor_id) {
        if ($ancestor_id == $pid) {
          return true;
        }
      }
    }
    return false;
  }
}
