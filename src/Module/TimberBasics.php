<?php

namespace TimberExtended\Module;

use Timber;
use TimberExtended;

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
        $site_class = TimberExtended::get_object_class('site', null, $context);

        $context['site'] = new $site_class();
        $context['title'] = self::page_title();
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
