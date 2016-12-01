<?php

namespace TimberExtended;

use Timber;

/**
 * Replace the core templating system with timber and inject some default
 * $context variables.
 *
 * 1. Wordpress templates-loader.php walks through all the different template
 *    types (embed, 404, single, singular etc) and picks the first one which
 *    has a template available.
 *
 * 2. Each get_${type}_template() creates a list of different template names
 *    that we manipulate with the ${type}_template_hierarchy filter and rename
 *    php to twig etc.
 *
 *  3. get_query_template() calls locate_template() that picks the first
 *     template available and returns it's name.
 *
 *  4. Back in templates-loader.php this final template name is filtered
 *     and set to `timber_index.php` using the template_include filter.
 *
 *  5. timber_index.php, provided in this plugin, calls Timber::render(), using
 *     the manipulated twig-version of the template file that was returned
 *     from get_query_template().
 *
 *  6. We hook in to timber/context and provide default $context variables for
 *     each page type. Theme's can further extend this using the same filter.
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
      add_filter("${type}_template_hierarchy", [$this, 'rename_template']);
    }

    add_filter('template_include', [$this, 'set_template_include']);
    add_filter('timber/context', [$this, 'add_timber_context'], -99, 1);
  }

  /**
   * Add default $context variables to Timber.
   */
  public function add_timber_context($context) {
    $page_type = \TimberExtended::get_page_type();

    switch ($page_type) {
      case 'embed': // Embedded post
      case 'single':
      case 'page':
      case 'singular':
        $context['post'] = new Timber\Post();

        $context['password_required'] = false;
        if (post_password_required($context['post']->ID)) {
          $context['password_required'] = true;
        }

        break;
      case 'attachment': // @todo
        $context['post'] = new Timber\Post();
        break;
      case 'search':
      case 'home': // Blog index
      case 'post_type_archive':
      case 'archive':
      case 'date':
        $context['posts'] = Timber::get_posts();
        break;
      case '404':
        break;
      case 'front_page': // Blog post index or static page.
        $post = get_post();
        if (isset($post)) {
          $context['post'] = new Timber\Post();
        } else {
          $context['posts'] = Timber::get_posts();
        }
        break;
      case 'tax':
      case 'category':
      case 'tag':
        $context['term'] = new Timber\Term();
        $context['posts'] = Timber::get_posts();

        if ($this->hasThemeFeature('context_add_terms') && $context['term']->taxonomy) {
          $context['terms'] = Timber::get_terms($context['term']->taxonomy);
        }
        break;
      case 'author':
        if ($author_query = get_query_var('author')) {
          $context['author'] = new Timber\User($author_query);
        }
        $context['posts'] = Timber::get_posts();
        break;
    }

    return $context;
  }

  /**
   * WP get_query_template() filters the template hierarchy and finds the
   * most prominent template to use. As WP can't include uncompiled twig
   * files, we swap this out for our own index.php which call Timber::render
   * with the theme's template.
   */
  public function set_template_include($template) {
    $GLOBALS['timber_extended_template'] = $template;
    return dirname(__DIR__) . '/timber_index.php';
  }

  /**
   * Rename the standard WP template filenames.
   */
  public function rename_template($templates) {
    // Transform the filenames to twig and BEM.
    if (apply_filters('timber_extended/templates/twig', true)) {
      foreach ($templates as $idx => $template) {
        // Look for twig files instead of php files.
        $template[$idx] = str_replace('.php', '.twig', $template);
      }
    }

    // Normalize archive templates
    if ($this->hasThemeFeature('normalize_archive_templates')) {
      $templates = $this->normalize_archive_templates($templates);
    }

    // Allow themes to use BEM naming convention
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
        $template_dir = str_replace(STYLESHEETPATH . '/', '', TEMPLATEPATH);
        $template_dir = trailingslashit($template_dir);

        if (mb_strpos($dir, $template_dir) === 0) {
          $dir = str_replace($template_dir, '', $dir);
        }
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
        $bem_template = "{$type}--{$suffix}";
        array_splice($templates, $idx, 0, $bem_template);
      }
    }
    return $templates;
  }

  /**
   * Normalize all category-like pages to also use an archive template.
   */
  protected function normalize_archive_templates($templates) {
    $archive_templates = [];

    switch (\TimberExtended::get_page_type()) {
      // @see get_category_template().
      case 'category':
      case 'tag':
        $term = get_queried_object();
        if (!empty($term->slug)) {
          $slug_decoded = urldecode($term->slug);
          if ($slug_decoded !== $term->slug) {
            $archive_templates[] = "archive-{$slug_decoded}.twig";
          }
          $archive_templates[] = "archive-{$term->slug}.twig";
          $archive_templates[] = "archive-{$term->term_id}.twig";
        }
      // @see get_taxonomy_template().
      case 'tax':
        $term = get_queried_object();
        if (!empty($term->slug)) {
          $taxonomy = $term->taxonomy;
          $slug_decoded = urldecode($term->slug);
          if ($slug_decoded !== $term->slug) {
            $archive_templates[] = "archive-{$taxonomy}-{$slug_decoded}.twig";
          }
          $archive_templates[] = "archive-{$taxonomy}-{$term->slug}.twig";
          $archive_templates[] = "archive-{$taxonomy}.twig";
        }
        break;
    }

    if (!empty($archive_templates)) {
      // Last two elements are archive.twig and index.twig
      array_splice($templates, -2, 0, $archive_templates);
    }
    return $templates;
  }

}

Templates::get_instance()->init();
