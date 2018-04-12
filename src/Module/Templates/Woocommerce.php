<?php

namespace TimberExtended\Module\Templates;

use Timber;

class Woocommerce
{
    /** @inheritdoc */
    public function __construct()
    {
        add_filter('wc_get_template', [__CLASS__, 'wc_get_template'], 10, 5);

        // Remove woocommerce own template loader.
        if ($this->woocommerce_verify_version('3.3')) {
            add_action('init', function () {
                remove_filter('template_include', ['WC_Template_Loader', 'template_loader']);
            }, 11);
        } else {
            remove_filter('template_include', ['WC_Template_Loader', 'template_loader']);
        }
    }

    /**
     * Render a WooCommerce template using Timber.
     *
     * @param mixed $located
     * @param string $template_name
     * @param array $args
     * @param string $template_path
     * @param string $default_path
     */
    public static function wc_get_template($located, $template_name, $args, $template_path, $default_path)
    {
        $templates = [];
        foreach (Timber\LocationManager::get_locations_theme_dir() as $dir) {
            $twig_template_name = str_replace('.php', '.twig', $template_name);
            $templates[] = trailingslashit($dir) . 'woocommerce/' . $twig_template_name;
            $templates[] = trailingslashit($dir) . 'woocommerce/' . $template_name;
            $templates[] = trailingslashit($dir) . $twig_template_name;
            $templates[] = trailingslashit($dir) . $template_name;
        }
        if ($twig_template_path = locate_template($templates)) {
            if (substr($twig_template_path, -5) !== '.twig') {
                return $twig_template_path;
            }
            $twig_template = str_replace(TEMPLATEPATH, '', $twig_template_path);
            Timber::render($twig_template, $args);
            return locate_template('index.php');
        }
        return $located;
    }

    /**
     * Check if the woocommerce version verifies.
     *
     * @param string $version
     * @param string $comparator
     * @return bool
     */
    protected function woocommerce_verify_version($version, $comparator = '>=') {
        if (class_exists('WooCommerce')) {
            global $woocommerce;
            if (version_compare($woocommerce->version, $version, $comparator)) {
                return true;
            }
        }
        return false;
    }
}
