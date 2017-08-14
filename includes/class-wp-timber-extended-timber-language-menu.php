<?php

namespace TimberExtended;

/**
 * A dummy Menu class which contains the WPML languages as it's items.
 *
 * @example
 * $context['language_menu'] = new TimberExtended\Menu('language-menu');
 * $context['language_menu']->setOptions(['skip_missing' => 0, 'orderby' => 'code', 'order' => 'desc']);
 *
 * {% for item in language_menu.get_items %}
 *   <li class="{{item.class}}">
 *     <a href="{{item.get_link}}" class="{{item.link_class}}">{{item.title}}</a>
 *   </li>
 * {% endfor %}
 */
class LanguageMenu extends Menu {

  public $options = [
    'skip_missing' => 0,
    'orderby' => 'code',
    'order' => 'desc',
  ];

  public function __construct($slug = 0) {
    $this->slug = $slug;
    $this->classPrefix = apply_filters('timber-extended/menu-class-prefix', $this->slug);
    $this->add_class($this->classPrefix);
    $this->init($this->slug);
  }

  public function setOptions($options) {
    $this->options = $options;
    $this->init($this->slug);
  }

  protected function init($menu_id) {
    $languages = apply_filters('wpml_active_languages', null, $this->options);
    if (empty($languages)) {
      $languages = [];
    }
    foreach ($languages as $langcode => &$language) {
      $language = (object) $language;
      $language->classes = array();
      $language->current = $language->active;
      $language->object_id = null;
      $language = new MenuItem($language);

      $language->classes = $language->link_classes = array();
      $language->class = $language->link_class = '';

      self::add_item_classes($language, $this->classPrefix);

      if (function_exists('PLL')) {
        $lang = PLL()->model->get_language($langcode);
        $language->disabled = isset($lang->active) ? !$lang->active : false;
        $language->enabled = !$language->disabled;
      }
    }
    $this->items = $languages;
  }

  public function get_items() {
    return $this->items;
  }
}
