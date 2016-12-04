<?php

class Debug_Bar_TimberExtended extends \Debug_Bar_Panel {
  private $template_suggestions;

  public function init() {
    $this->template_suggestions = array();
    $this->title('TimberExtended');
    add_filter('timber_extended/templates/suggestions', array($this, 'add_template_suggestions'), 100, 1);
  }

  public function add_template_suggestions($templates) {
    $this->template_suggestions[] = $templates;
    return $templates;
  }

  public function prerender() {
    $this->set_visible(true);
  }

  public function render() {
    $page_types = \TimberExtended::get_page_types();

    if (function_exists('d')) {
      d(Timber::get_context());
    }

    echo '<h5>' . sprintf(__('These were the templates search for detected page types: <em>%s</em>'), implode(', ', $page_types)) . '</h5>';

    foreach ($this->template_suggestions as $templates) {
      echo '<ul>';
      echo '<li>';
      echo implode('</li><li>', $templates);
      echo '</li>';
      echo '</ul>';
    }
  }
}
