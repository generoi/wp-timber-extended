<?php

namespace TimberExtended\Module\Templates;

use Timber;

class Tailor
{
    public function __construct()
    {
        add_filter('tailor_partial', [__CLASS__, 'tailor_partial'], 10, 4);
    }

    /**
     * Render a tailor partial using Timber.
     *
     * @param string $partial
     * @param string $slug
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public static function tailor_partial($partial, $slug, $name, $args = [])
    {
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
}
