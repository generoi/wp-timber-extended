<?php

namespace TimberExtended;

use Timber;
use TimberExtended;
use WPSEO_Options;

class Site extends Timber\Site
{
    public $ImageClass = 'Timber\Image';

    public $icon;
    public $logo;
    public $social;
    public $theme_options;

    public function __construct($site_name_or_id = null)
    {
        if ($this->ImageClass === 'Timber\Image') {
            $this->ImageClass = TimberExtended::get_object_class('image', null, $this);
        }
        parent::__construct($site_name_or_id);
    }

    protected function init()
    {
        parent::init();

        $this->theme_options = get_theme_mods();

        if ($icon = get_site_icon_url()) {
            $this->icon = new $this->ImageClass(get_site_icon_url());
        }
        if ($logo_id = get_theme_mod('custom_logo')) {
            $this->logo = new $this->ImageClass($logo_id);
        }

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
}


