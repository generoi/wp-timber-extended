<?php

namespace TimberExtended\Module;

use Timber;
use TimberExtended;

/**
 * Replace the core templating system with timber and inject some default
 * $context variables.
 *
 * @sse wp-includes/template-loader.php
 * @see get_query_template().
 */
class Templates extends Module
{
    public function __construct()
    {
        parent::__construct();

        $legacy_widget_filter = apply_filters('timber-extended/timber-widget', null);
        $legacy_woo_filter = apply_filters('timber_extended/templates/twig', null);

        // Widget integration
        if ($this->has_theme_feature('widget') || (isset($legacy_widget_filter) && $legacy_widget_filter)) {
            new Templates\Widget();
        }

        // Tailor integration
        if ($this->has_theme_feature('tailor')) {
            new Templates\Tailor();
        }

        // WooCommerce integration
        if ($this->has_theme_feature('woocommerce') || (isset($legacy_woo_filter) && $legacy_woo_filter)) {
            new Templates\Woocommerce();
        }

        // @see get_query_template().
        foreach ([
            'index', '404', 'archive', 'author', 'category', 'tag', 'taxonomy',
            'date', 'embed', 'home', 'frontpage', 'page', 'paged', 'search',
            'single', 'singular', 'attachment', 'timber'
        ] as $type) {
            add_filter("${type}_template_hierarchy", [$this, 'add_template_suggestions']);
        }

        add_filter('timber/context', [$this, 'add_default_context'], -99, 1);

        // Do not override tailors filter.
        if (!function_exists('tailor') || !tailor()->is_tailoring()) {
            add_filter('template_include', [__CLASS__, 'set_template_include'], ~PHP_INT_MAX);
        }
    }

   /**
    * Add default objects to the Timber $context variable.
    *
    * @param array $context
    * @return array
    */
    public function add_default_context($context)
    {
        if (TimberExtended::is_page_type(['embed', 'single', 'page', 'singular'])) {
            // Single posts
            $post = get_post();
            $post_class = TimberExtended::get_object_class('post', null, $post);
            $context['post'] = new $post_class($post);

            $context['password_required'] = false;
            if (post_password_required($context['post']->ID)) {
                $context['password_required'] = true;
            }
        } elseif (TimberExtended::is_page_type(['attachment'])) {
            // Attachments
            $post = get_post();
            $post_class = TimberExtended::get_object_class('post', null, $post);
            $context['post'] = new $post_class($post);
        } elseif (TimberExtended::is_page_type(['search', 'home', 'post_type_archive', 'date'])) {
            // Archive pages without an actual object.
            $context['posts'] = new Timber\PostQuery();
        } elseif (TimberExtended::is_page_type(['front_page'])) {
            // Frontpage which has either a post object or a list of latest posts.
            $post = get_post();
            if (isset($post)) {
                $post_class = TimberExtended::get_object_class('post', null, $post);
                $context['post'] = new $post_class($post);
            } else {
                $context['posts'] = new Timber\PostQuery();
            }
        } elseif (TimberExtended::is_page_type(['tax', 'category', 'tag'])) {
            // Archive pages with a term object.
            $term = get_queried_object();
            $term_class = TimberExtended::get_object_class('term', null, $term);
            $context['term'] = new $term_class($term);

            $context['posts'] = new Timber\PostQuery();
        } elseif (TimberExtended::is_page_type(['author'])) {
            // Author pages.
            if ($author_query = get_query_var('author')) {
                $user = get_userdata($author_query);
                $user_class = TimberExtended::get_object_class('user', null, $user);
                $context['author'] = new $user_class($user);
            }
            $context['posts'] = new Timber\PostQuery();
        }

        return $context;
    }

   /**
    * WP get_query_template() filters the template hierarchy and finds the
    * most prominent template to use. Rather than returning it for inclusion,
    * we render it with Timber.
    *
    * @param string $template Template path
    * @return string
    */
    public static function set_template_include($template)
    {
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

   /**
    * Add template suggestions.
    *
    * @param array $templates List of template suggestions
    * @return array
    */
    public function add_template_suggestions($templates)
    {
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
        if ($this->has_theme_feature('bem_templates')) {
            $templates = $this->add_bem_templates($templates);
        }

        // Remove the extra directories WP detects eg. when custom templates are
        // in views/ subfolder.
        foreach (Timber\LocationManager::get_locations_theme_dir() as $dir) {
            foreach ($templates as $idx => $template) {
                if (strpos($template, $dir) === 0) {
                    $template = str_replace(trailingslashit($dir), '', $template);
                    $templates[$idx] = $template;
                }
            }
        }

        // Search for templates with the same paths as Timber::$dirname.
        foreach (Timber\LocationManager::get_locations_theme_dir() as $dir) {
            foreach ($templates as $idx => $template) {
                // If this timber path is a subdirectory, remove the base
                // template: views/page.twig
                // dir: views/pages
                // timber_root_dir: views
                // template: views/pages/page.twig
                $timber_root_dir = explode('/', $dir);
                $timber_root_dir = $timber_root_dir[0];
                if (strpos($template, trailingslashit($timber_root_dir)) === 0) {
                    $template = substr($template, strlen(trailingslashit($timber_root_dir)));
                }

                if (strpos($template, $dir) !== 0) {
                    array_splice($templates, $idx, 0, trailingslashit($dir) . $template);
                }
            }
        }
        $templates = array_unique($templates);
        $templates = apply_filters('timber_extended/templates/suggestions', $templates);
        return $templates;
    }

    /**
     * Use BEM-naming for templates.
     *
     * @param array $templates
     * @return aray
     *
     * @example single.twig => single.twig
     * @example single-{post_type}-{post_name}.twig => single--{post_type}-{post_name}.twig
     */
    protected function add_bem_templates($templates)
    {
        foreach ($templates as $idx => $template) {
            if (preg_match('/([^-]+)-(.*)/', $template, $matches)) {
                list(, $type, $suffix) = $matches;
                array_splice($templates, $idx, 0, "${type}--${suffix}");
            }
        }
        return $templates;
    }
}
