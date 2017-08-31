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
        remove_filter('template_include', ['WC_Template_Loader', 'template_loader']);
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
        $twig_template_name = str_replace('.php', '.twig', $template_name);
        $twig_template = wc_locate_template($twig_template_name);
        if (file_exists($twig_template)) {
            $twig_template = str_replace(TEMPLATEPATH, '', $twig_template);
            Timber::render($twig_template, $args);
            return locate_template('index.php');
        }
        return $located;
    }
}
