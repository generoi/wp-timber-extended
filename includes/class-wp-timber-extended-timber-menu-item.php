<?php

namespace TimberExtended;

use \Timber;

/**
 * An extended Timber\MenuItem which can store both item classes and link
 * classes.
 *
 * @example
 * {% for item in menu.get_items %}
 *   <li class="{{item.class}}">
 *     <a href="{{item.get_link}}" class="{{item.link_class}}">{{item.title}}</a>
 *   </li>
 * {% endfor %}
 */
class MenuItem extends Timber\MenuItem {
  public $link_classes = array();
  public $link_class = '';

  public function add_link_class($class_name) {
    $this->link_classes[] = $class_name;
    $this->link_class .= ' '. $class_name;
  }
}
