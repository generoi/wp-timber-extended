<?php

namespace TimberExtended;

use Timber;

/**
 * Replace the core templating system with timber and inject some default
 * $context variables.
 *
 * @sse wp-includes/template-loader.php
 * @see get_query_template().
 */
class Templates extends \TimberExtended {

  public function init() {
    $features = get_theme_support('timber-extended-templates');
    if (!empty($features[0])) {
      $this->setThemeFeatures($features[0]);
    }

    // @see get_query_template().
    foreach ([
      'index', '404', 'archive', 'author', 'category', 'tag', 'taxonomy',
      'date', 'embed', 'home', 'frontpage', 'page', 'paged', 'search',
      'single', 'singular', 'attachment'
    ] as $type) {
      add_filter("${type}_template_hierarchy", [$this, 'add_template_suggestions']);
    }

    add_filter('template_include', [$this, 'set_template_include']);
    add_filter('timber/context', [$this, 'add_default_context'], -99, 1);
  }

  /**
   * Add default $context variables to Timber.
   */
  public function add_default_context($context) {
    if (\TimberExtended::is_page_type(['embed', 'single', 'page', 'singular'])) {
      $context['post'] = new Timber\Post();

      $context['password_required'] = false;
      if (post_password_required($context['post']->ID)) {
        $context['password_required'] = true;
      }

    } elseif (\TimberExtended::is_page_type(['attachment'])) {
      $context['post'] = new Timber\Post();
    }

    elseif (\TimberExtended::is_page_type(['search', 'home', 'post_type_archive', 'date'])) {
      $context['posts'] = Timber::get_posts();
    }

    elseif (\TimberExtended::is_page_type(['front_page'])) {
      $post = get_post();
      if (isset($post)) {
        $context['post'] = new Timber\Post();
      } else {
        $context['posts'] = Timber::get_posts();
      }
    }

    elseif (\TimberExtended::is_page_type(['tax', 'category', 'tag'])) {
      $context['term'] = new Timber\Term();
      $context['posts'] = Timber::get_posts();
    }

    elseif (\TimberExtended::is_page_type(['author'])) {
      if ($author_query = get_query_var('author')) {
        $context['author'] = new Timber\User($author_query);
      }
      $context['posts'] = Timber::get_posts();
    }

    return $context;
  }

  /**
   * WP get_query_template() filters the template hierarchy and finds the
   * most prominent template to use. Rather than returning it for inclusion,
   * we render it with Timber.
   */
  public function set_template_include($template) {
    $context = Timber::get_context();
    $context['template_file'] = basename($template);
    list($template_type) = explode('-', str_replace('.twig', '', $context['template_file']));
    $context['template_type'] = $template_type;

    Timber::render($template, $context);
    return false;
  }

  /**
   * Add template suggestions.
   */
  public function add_template_suggestions($templates) {
    // Suggest twig templates before php templates.
    if (apply_filters('timber_extended/templates/twig', true)) {
      foreach ($templates as $idx => $template) {
        array_splice($templates, $idx, 0, str_replace('.php', '.twig', $template));
      }
    }

    // Support BEM-baming conventions.
    if ($this->hasThemeFeature('bem_templates')) {
      $templates = $this->add_bem_templates($templates);
    }

    // Search for templates with the same paths as Timber::$dirname.
    $locations = [];
    foreach (Timber\LocationManager::get_locations_theme_dir() as $dir) {
      // Sage manipulates the location of the TEMPLATEPATH as such:
      // STYLESHEETPATH    -> /var/www/wordpress/web/app/themes/sage
      // TEMPLATEPATH     -> /var/www/wordpress/web/app/themes/sage/templates
      if (TEMPLATEPATH != STYLESHEETPATH) {
        $template_dir = trailingslashit(str_replace(STYLESHEETPATH . '/', '', TEMPLATEPATH));
        $dir = strpos($dir, $template_dir) === 0 ? str_replace($template_dir, '', $dir) : $dir;
      }

      foreach ($templates as $template) {
        $locations[] = trailingslashit($dir) . $template;
      }
    }
    // Add al the directories listed in Timber::$dirname, after the
    // default TEMPLATEPATH directory.
    $templates = array_merge($templates, $locations);
    $templates = apply_filters('timber_extended/templates/suggestions', $templates);

    return $templates;
  }

  /**
   * Use BEM-naming for templates.
   *
   * @example
   * single.twig => single.twig
   * single-{post_type}-{post_name}.twig => single--{post_type}-{post_name}.twig
   */
  protected function add_bem_templates($templates) {
    foreach ($templates as $idx => $template) {
      if (preg_match('/([^-]+)-(.*)/', $template, $matches)) {
        list(, $type, $suffix) = $matches;
        array_splice($templates, $idx, 0, "${type}--${suffix}");
      }
    }
    return $templates;
  }
}

Templates::get_instance()->init();
