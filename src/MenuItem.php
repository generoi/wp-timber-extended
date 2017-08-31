<?php

namespace TimberExtended;

use Timber;
use TimberExtended;

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
class MenuItem extends Timber\MenuItem
{
    public $link_classes = [];
    public $link_class = '';
    public $classPrefix = '';

    public function __construct($data)
    {
        $this->PostClass = TimberExtended::get_object_class('post', null, $this);

        parent::__construct($data);
    }

    public function add_link_class($class_name)
    {
        $this->link_classes[] = $class_name;
        $this->link_class .= ' '. $class_name;
    }

    public function remove_class($class_name)
    {
        $this->classes = array_diff($this->classes, [$class_name]);
        $this->class = implode(' ', $this->classes);
    }

    public function get_children()
    {
        $children = parent::get_children();
        return Menu::recurse_item_classes($children, $this->classPrefix);
    }
}
