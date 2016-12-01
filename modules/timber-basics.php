<?php

namespace TimberExtended;

use Timber;

class TimberBasics extends \TimberExtended {

  public function init() {
    add_filter('timber/context', [$this, 'add_timber_context'], 1, 1);
  }

  public function add_timber_context($context) {
    $context['site']->logo = new Timber\Image(get_site_icon_url());
    // Timber doesn't support bedrock-like directory structures.
    $context['site']->siteurl = get_site_url();
  }
}

TimberBasics::get_instance()->init();
