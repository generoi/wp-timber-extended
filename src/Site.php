<?php

namespace TimberExtended;

use Timber;
use TimberExtended;
use WPSEO_Options;

class Site extends Timber\Site
{
    public $ImageClass = 'Timber\Image';

    public $social;

    /** @inheritdoc */
    public function __construct($site_name_or_id = null)
    {
        if ($this->ImageClass === 'Timber\Image') {
            $this->ImageClass = TimberExtended::get_object_class('image', null, $this);
        }
        parent::__construct($site_name_or_id);
    }

    /** @inheritdoc */
    protected function init()
    {
        parent::init();

        // Add Yoast social options if available.
        if (class_exists('WPSEO_Options')) {
            $this->social = WPSEO_Options::get_option('wpseo_social');
        }

        // Fix incorrect home url when Polylang is used
        if (function_exists('pll_home_url')) {
            $this->url = pll_home_url();
            $this->home_url = $this->url;
        }
    }

    /** @inheritdoc */
    public function icon()
    {
        if ($icon = parent::icon()) {
            return $this->icon = new $this->ImageClass($icon);
        }
    }

    /**
     * Get the site's custom logo.
     */
    public function logo()
    {
        if ($logo_id = get_theme_mod('custom_logo')) {
            return new $this->ImageClass($logo_id);
        }
    }

    /**
     * Retrieve theme options.
     */
    public function theme_options($option = null)
    {
        if (!isset($this->theme_options)) {
            $this->theme_options = get_theme_mods();
        }

        if ($option) {
            return isset($this->theme_options[$option]) ? $this->theme_options[$option] : null;
        }
        return $this->theme_options;
    }
}


