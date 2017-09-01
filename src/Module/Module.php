<?php

namespace TimberExtended\Module;

class Module
{
    /** @var array $features List of theme enabled features for the module */
    protected $features = [];

    public function __construct()
    {
        $features = $this->get_features();
        if (!empty($features[0])) {
            $this->features = $features[0];
        }
    }

    /**
     * Get the theme support options for the calling module.
     *
     * @return array
     */
    public function get_features()
    {
        $class_name = get_called_class();
        $feature = self::get_feature_name($class_name);
        return get_theme_support($feature);
    }

    /**
     * Return if feature is active.
     *
     * @param string $feature
     * @return bool
     */
    public function has_theme_feature($feature)
    {
        return in_array($feature, $this->features);
    }

    /**
     * Load theme feature modules.
     */
    public static function load_modules()
    {
        foreach (glob(__DIR__ . '/*.php') as $file) {
            $class_name = __NAMESPACE__ . '\\' . basename($file, '.php');
            $feature = self::get_feature_name($class_name);
            if (get_theme_support($feature)) {
                new $class_name();
            }
        }
    }

    /**
     * Get the feature name based on a class name.
     *
     * @param string $class_name
     * @return string
     */
    protected static function get_feature_name($class_name)
    {
        $class_name = explode('\\', $class_name);
        $class_name = array_pop($class_name);
        $feature_name = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $class_name));
        $feature_name = 'timber-extended-' . $feature_name;
        return $feature_name;
    }
}
