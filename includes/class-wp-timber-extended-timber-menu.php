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

  public function remove_class($class_name) {
    $this->classes = array_diff($this->classes, [$class_name]);
    $this->class = implode(' ', $this->classes);
  }

  public function get_items() {
    if (!isset($this->_recursed_items)) {
      $this->_recursed_items = parent::get_items();
      $this->_recursed_items = $this->recurse_item_classes($this->_recursed_items, $this->classPrefix);
    }
    return $this->_recursed_items;
  }

  public static function recurse_item_classes($items, $prefix) {
    if (!$items) {
      return $items;
    }
    foreach ($items as $item) {
      // Recurse.
      $item->children = self::recurse_item_classes($item->children, $prefix);
      self::add_item_classes($item, $prefix);
    }
    return $items;
  }

  public static function add_item_classes($item, $prefix) {
    $item->classPrefix = $prefix;

    $item->add_class($prefix . '__item');
    $item->add_link_class($prefix . '__link');

    if (self::is_active($item)) {
      $item->add_class($prefix . '__item--active');
      $item->add_link_class($prefix . '__link--active');
      $item->add_class('active');
    }
  }

  public static function is_active($item) {
    $active = $item->current || $item->current_item_ancestor || $item->current_item_parent || static::is_childpage($item->menu_object->object_id);
    if ($active) {
      return $active;
    }
    if ($item->children) {
      foreach ($item->children as $child) {
        if (self::is_active($child)) {
          return true;
        }
      }
    }
    return false;
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
