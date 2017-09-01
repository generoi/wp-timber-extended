<?php

namespace TimberExtended\Module;

use Timber;
use TimberExtended;
use WPSEO_Options;

class TimberBasics extends Module
{
    /** @inheritdoc */
    public function __construct()
    {
        parent::__construct();

        add_filter('timber/context', [$this, 'add_timber_context'], 9, 1);
        add_filter('timber/cache/location', [$this, 'timber_cache_location']);
    }

    /**
     * Set the Timber cache location.
     *
     * @paran string
     */
    public function timber_cache_location()
    {
        return WP_CONTENT_DIR . '/cache/timber';
    }

    /**
     * Attach values to global timber context.
     *
     * @param array $context
     * return array
     */
    public function add_timber_context($context)
    {
        $context['site']->theme_options = get_theme_mods();
        $context['title'] = self::page_title();
        $image_class = TimberExtended::get_object_class('image');

        if ($icon = get_site_icon_url()) {
            $context['site']->icon = new $image_class(get_site_icon_url());
        }
        if ($logo_id = get_theme_mod('custom_logo')) {
            $context['site']->logo = new $image_class($logo_id);
        }
        // Timber doesn't support bedrock-like directory structures.
        $context['site']->siteurl = get_site_url();

        // Add Yoast social options if available.
        if (class_exists('WPSEO_Options')) {
            $context['site']->social = WPSEO_Options::get_option('wpseo_social');
        }
        return $context;
    }

    /**
     * Get the page title.
     *
     * @return string
     */
    public static function page_title()
    {
        if (is_home()) {
            if ($home = get_option('page_for_posts', true)) {
                return get_the_title($home);
            }
            return __('Latest Posts', 'wp-timber-extended');
        }
        if (is_archive()) {
            return get_the_archive_title();
        }
        if (is_search()) {
            return sprintf(__('Search Results for %s', 'wp-timber-extended'), get_search_query());
        }
        if (is_404()) {
            return __('Not Found', 'wp-timber-extended');
        }
        return get_the_title();
    }
}
