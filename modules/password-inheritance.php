<?php

namespace TimberExtended;

class PasswordInheritance extends \TimberExtended {

  public function init() {
    add_filter('timber/context', [$this, 'add_timber_context'], 1, 1);
  }

  public function add_timber_context($context) {
    if (!empty($context['password_required']) || !is_page()) {
      return $context;
    }
    if (self::post_ancestor_password_required($context['post']->ID)) {
      $context['password_required'] = true;
    }
    return $context;
  }

  public static function post_ancestor_password_required($pid) {
    $post = get_post($pid);
    if ($post->post_parent) {
      foreach (get_post_ancestors($pid) as $ancestor_id) {
        if (post_password_required($ancestor_id)) {
          return true;
        }
      }
    }
    return false;
  }
}

PasswordInheritance::get_instance()->init();
