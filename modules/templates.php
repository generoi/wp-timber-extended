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

  const DEFAULT_WIDGET_CLASS = 'TimberExtended\\Widget';

  public function init() {
    $features = get_theme_support('timber-extended-templates');
    if (!empty($features[0])) {
      $this->setThemeFeatures($features[0]);
    }

    // Render widgets with Twig.
    if (apply_filters('timber-extended/timber-widget', true)) {
      add_filter('dynamic_sidebar_params', [$this, 'wrap_widget_output']);
    }

    // @see get_query_template().
    foreach ([
      'index', '404', 'archive', 'author', 'category', 'tag', 'taxonomy',
      'date', 'embed', 'home', 'frontpage', 'page', 'paged', 'search',
      'single', 'singular', 'attachment'
    ] as $type) {
      add_filter("${type}_template_hierarchy", [$this, 'add_template_suggestions']);
    }

    // add_filter('wc_get_template_part', [$this, 'add_wc_template_suggestions'], 10, 3);
    add_filter('wc_get_template', [$this, 'wc_get_template'], 10, 5);
    // Remove woocommerce own template loader.
    remove_filter('template_include', ['WC_Template_Loader', 'template_loader']);

    add_filter('tailor_partial', [$this, 'tailor_partial'], 10, 4);

    add_filter('timber/context', [$this, 'add_default_context'], -99, 1);
    // Do not override tailors filter.
    if (!function_exists('tailor') || !tailor()->is_tailoring()) {
        add_filter('template_include', [$this, 'set_template_include'], ~PHP_INT_MAX);
    }
  }

  public function tailor_partial($partial, $slug, $name, $args = array()) {
    $theme_partial_dir = apply_filters('tailor_theme_partial_dir', 'tailor/');
    $theme_partial_dir = trailingslashit($theme_partial_dir);
    $templates = [
      "{$theme_partial_dir}/{$slug}--{$name}.twig",
      "{$theme_partial_dir}/{$slug}-{$name}.twig",
    ];
    if ($template = locate_template($templates)) {
      do_action("tailor_partial_{$slug}", $partial, $slug, $name);
      $template = str_replace(TEMPLATEPATH, '', $template);
      $context = $args;
      Timber::render($template, $context);
      return false;
    }
    return $partial;
  }

  /**
   * Replace the original display callback with our own wrapper.
   */
  public function wrap_widget_output($sidebar_params) {
    if (is_admin()) {
      return $sidebar_params;
    }
    global $wp_registered_widgets;
    $current_widget_id = $sidebar_params[0]['widget_id'];
    $wp_registered_widgets[$current_widget_id]['original_callback'] = $wp_registered_widgets[$current_widget_id]['callback'];
    $wp_registered_widgets[$current_widget_id]['callback'] = [$this, 'display_widget'];
    return $sidebar_params;
  }

  /**
   * Render a widget.
   */
  public function display_widget() {
    global $wp_registered_widgets;
    $original_callback_params = func_get_args();
    $widget_id   = $original_callback_params[0]['widget_id'];
    $widget_name = $original_callback_params[0]['widget_name'];
    $sidebar_id  = $original_callback_params[0]['id'];
    $original_callback = $wp_registered_widgets[$widget_id]['original_callback'];
    $wp_registered_widgets[$widget_id]['callback'] = $original_callback;

    if (is_callable($original_callback)) {
        $widget = $original_callback[0];

        ob_start();
        call_user_func_array($original_callback, $original_callback_params);
        if ($widget_output = ob_get_clean()) {
            $widget_output = $this->widget_output($widget_output, $widget_id, $widget_name, $widget, $sidebar_id);
            echo apply_filters('widget_output', $widget_output, $widget_id, $widget_name, $widget, $sidebar_id);
        }
    }
  }

  /**
   * Wrap widget output with a timber template.
   */
  public function widget_output($output, $widget_id, $widget_name, $widget, $sidebar_id) {
    // ACFW hardcodes this value when no template is found.
    if (strpos($output, 'No template found') !== FALSE) {
        $output = NULL;
    }
    $settings = $widget->get_settings();
    $settings = isset($settings[$widget->number]) ? $settings[$widget->number] : $settings;

    $class_name = apply_filters('timber_extended/class_name', self::DEFAULT_WIDGET_CLASS, ['widget'], $widget);
    $context['widget'] = new $class_name('widget_' . $widget_id);
    $context['widget']->init(array_merge($settings, [
        'widget_id' => $widget_id,
        'sidebar_id' => $sidebar_id,
        'content' => $output,
    ]));

    $templates = apply_filters('timber_extended/templates/suggestions', [
        'widgets/widget--' . $widget_id . '.twig',
        // @todo widget->slug?
        'widgets/widget--' . strtolower(sanitize_html_class(str_replace(' ', '_', $widget_name))) . '.twig',
        'widgets/widget--' . $sidebar_id . '.twig',
        'widgets/widget.twig',
    ]);

    $output = Timber::fetch($templates, $context);
    return $output;
  }

  /**
   * Add default $context variables to Timber.
   */
  public function add_default_context($context) {
    if (\TimberExtended::is_page_type(['embed', 'single', 'page', 'singular'])) {
      $context['post'] = Timber\PostGetter::get_post(get_the_ID());

      $context['password_required'] = false;
      if (post_password_required($context['post']->ID)) {
        $context['password_required'] = true;
      }

    } elseif (\TimberExtended::is_page_type(['attachment'])) {
      $context['post'] = Timber\PostGetter::get_post(get_the_ID());
    }

    elseif (\TimberExtended::is_page_type(['search', 'home', 'post_type_archive', 'date'])) {
      $context['posts'] = new Timber\PostQuery();
    }

    elseif (\TimberExtended::is_page_type(['front_page'])) {
      $post = get_post();
      if (isset($post)) {
        $context['post'] = Timber\PostGetter::get_post(get_the_ID());
      } else {
        $context['posts'] = new Timber\PostQuery();
      }
    }

    elseif (\TimberExtended::is_page_type(['tax', 'category', 'tag'])) {
      $context['term'] = new Timber\Term();
      $context['posts'] = new Timber\PostQuery();
    }

    elseif (\TimberExtended::is_page_type(['author'])) {
      if ($author_query = get_query_var('author')) {
        $context['author'] = new Timber\User($author_query);
      }
      $context['posts'] = new Timber\PostQuery();
    }

    return $context;
  }

  /**
   * WP get_query_template() filters the template hierarchy and finds the
   * most prominent template to use. Rather than returning it for inclusion,
   * we render it with Timber.
   */
  public function set_template_include($template) {
    if (substr($template, -5) != '.twig') {
      return $template;
    }
    // If the path is absolute, use the relative path from the theme.
    $template = str_replace(TEMPLATEPATH, '', $template);

    $context = Timber::get_context();
    $context['template_file'] = basename($template);
    list($template_type) = explode('-', str_replace('.twig', '', $context['template_file']));
    $context['template_type'] = $template_type;

    $html = Timber::fetch($template, $context);
    if (trim($html)) {
      echo $html;
    } else {
      echo sprintf(__('Template %s did not output any content.', 'wp-timber-extended'), $template);
    }
    return locate_template('index.php');
  }

  public function wc_get_template($located, $template_name, $args, $template_path, $default_path) {
    if (apply_filters('timber_extended/templates/twig', true)) {
      $twig_template_name = str_replace('.php', '.twig', $template_name);
      $twig_template = wc_locate_template($twig_template_name);
      if (file_exists($twig_template)) {
        $twig_template = str_replace(TEMPLATEPATH, '', $twig_template);
        Timber::render($twig_template, $args);
        return locate_template('index.php');
      }
    }
    return $located;
  }

  /**
   * Add template suggestions.
   */
  public function add_template_suggestions($templates) {
    // If tailored, add a tailor variation.
    if (apply_filters('timber_extended/templates/twig', true) && function_exists('tailor')) {
      if ((is_page() || is_single()) && tailor()->is_tailored()) {
        foreach ($templates as $idx => $template) {
          array_splice($templates, $idx, 0, str_replace('.php', '-tailor.php', $template));
        }
      }
    }

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
    foreach (Timber\LocationManager::get_locations_theme_dir() as $dir) {
      // Sage manipulates the location of the TEMPLATEPATH as such:
      // STYLESHEETPATH    -> /var/www/wordpress/web/app/themes/sage
      // TEMPLATEPATH     -> /var/www/wordpress/web/app/themes/sage/templates
      if (TEMPLATEPATH != STYLESHEETPATH) {
        $template_dir = trailingslashit(str_replace(STYLESHEETPATH . '/', '', TEMPLATEPATH));
        $dir = strpos($dir, $template_dir) === 0 ? str_replace($template_dir, '', $dir) : $dir;
      }

      foreach ($templates as $idx => $template) {
        array_splice($templates, $idx, 0, trailingslashit($dir) . $template);
      }
    }
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
