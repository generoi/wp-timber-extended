<?php
/*
Plugin Name:        Timber Extended
Plugin URI:         http://genero.fi
Description:        Replace Wordpress templating system with timber and extend it further.
Version:            2.0.0-alpha.5
Author:             Genero
Author URI:         http://genero.fi/

License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
    exit;
}

class TimberExtended
{
    private static $instance = null;
    public $version = '2.0.0';
    public $plugin_name = 'wp-timber-extended';
    public $github_url = 'https://github.com/generoi/wp-timber-extended';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        Puc_v4_Factory::buildUpdateChecker($this->github_url, __FILE__, $this->plugin_name);
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        add_action('after_setup_theme', ['TimberExtended\\Module\\Module', 'load_modules'], 99);
        add_action('debug_bar_panels', [$this, 'add_debug_bar']);
    }

    /**
     * Add a custom debug bar section showing template suggestions.
     *
     * @param array $panels
     * @return array
     */
    public function add_debug_bar($panels)
    {
        // Cannot use namespaces and therefore no autoloading.
        require_once __DIR__ . '/src/DebugBar.php';
        $panels[] = new TimberExtended_DebugBar();
        return $panels;
    }

    /**
     * Return if current page matches the type.
     *
     * @param string $type Type as defined in get_page_types().
     * @return bool
     */
    public static function is_page_type($type)
    {
        if (is_string($type)) {
            $type = [$type];
        }
        return !empty(array_intersect($type, self::get_page_types()));
    }

    /**
     * Get matching page types of the current page.
     *
     * @return array
     */
    public static function get_page_types()
    {
        $types = [];
        if (is_embed()) {
            $types[] = 'embed';
        }
        if (is_404()) {
            $types[] = '404';
        }
        if (is_search()) {
            $types[] = 'search';
        }
        if (is_front_page()) {
            $types[] = 'front_page';
        }
        if (is_home()) {
            $types[] = 'home';
        }
        if (is_post_type_archive()) {
            $types[] = 'post_type_archive';
        }
        if (is_tax()) {
            $types[] = 'tax';
        }
        if (is_attachment()) {
            $types[] = 'attachment';
        }
        if (is_single()) {
            $types[] = 'single';
        }
        if (is_page()) {
            $types[] = 'page';
        }
        if (is_singular()) {
            $types[] = 'singular';
        }
        if (is_category()) {
            $types[] = 'category';
        }
        if (is_tag()) {
            $types[] = 'tag';
        }
        if (is_author()) {
            $types[] = 'author';
        }
        if (is_date()) {
            $types[] = 'date';
        }
        if (is_archive()) {
            $types[] = 'archive';
        }

        return apply_filters('timber_extended/templates/page_types', $types);
    }

    /**
     * Helper for object getter functions.
     *
     * @internal
     * @param string $type Object type (post, term, image, etc)
     * @param mixed $object Object or list of objects
     * @param string $class_name Class to create objects with
     * @return mixed
     */
    public static function object_getter($type, $object, $class_name = null)
    {
        // If no class name is specified, figure it out.
        $is_guess_class_name = !isset($class_name);
        if ($is_guess_class_name) {
            $class_name = TimberExtended::get_object_class($type, null, $object);
        }

        if (is_array($object)) {
            if (Timber\Helper::is_array_assoc($object)) {
                foreach ($object as $key => &$obj) {
                    if ($is_guess_class_name) {
                        $obj = self::object_create($type, $obj, $class_name);
                    } else {
                        $obj = new $class_name($obj);
                    }
                }
            } else {
                foreach ($object as &$obj) {
                    if ($is_guess_class_name) {
                        $obj = self::object_create($type, $obj, $class_name);
                    } else {
                        $obj = new $class_name($obj);
                    }
                }
            }
            return $object;
        }
        if ($is_guess_class_name) {
            $obj = self::object_create($type, $object, $class_name);
        } else {
            $obj = new $class_name($object);
        }
        return $obj;
    }

    /**
     * Create a Timber object using the correct Timber class.
     *
     * @param string $type Object type (post, term, image, etc)
     * @param mixed $object Object
     * @param string $class_name Class to create objects with
     * @return mixed
     */
    protected static function object_create($type, $object, $class_name)
    {
        if (!is_object($object) || get_class($object) !== $class_name) {
            $object = new $class_name($object);
        }
        // Verify that the class is correct
        $object_class_name = self::get_object_class($type, null, $object);
        if ($class_name !== $object_class_name) {
            $object = new $object_class_name($object);
        }
        return $object;
    }

    /**
     * Get the Timber class to use when initializing an object.
     *
     * @param string $type Object type (post, term, user, image, widget)
     * @param string $class_name The default class name
     * @param object $object The associated object if it exists.
     * @return string
     */
    public static function get_object_class($type, $class_name = null, $object = null)
    {
        if (!isset($class_name)) {
            switch ($type) {
                case 'site':
                    $class_name = 'TimberExtended\\Site';
                    break;
                case 'image':
                    $class_name = 'TimberExtended\\Image';
                    break;
                case 'post':
                    $class_name = 'TimberExtended\\Post';
                    break;
                case 'widget':
                    $class_name = 'TimberExtended\\Widget';
                    break;
                case 'term':
                    $class_name = 'TimberExtended\\Term';
                    break;
                case 'user':
                    $class_name = 'TimberExtended\\User';
                    break;
                case 'widget':
                    $class_name = 'TimberExtended\\Widget';
                    break;
                case 'menuitem':
                    $class_name = 'TimberExtended\\MenuItem';
                    break;
                case 'menu':
                    $class_name = 'TimberExtended\\Menu';
                    break;
            }
        }
        $class_name = apply_filters("timber_extended/class", $class_name, $type, $object);
        $class_name = apply_filters("timber_extended/$type/class", $class_name, $object);

        if ($type === 'post') {
            if (!isset($object)) {
                $object = get_post();
            }
            if (isset($object->post_type)) {
                // Unfold PostGetter::get_post_class() to avoid recursion.
                $post_class = apply_filters('Timber\PostClassMap', $class_name);
                if (is_string($post_class)) {
                    $class_name = $post_class;
                } elseif (is_array($post_class) && isset($post_class[$object->post_type])) {
                    $class_name = $post_class[$object->post_type];
                }
            }
        }

        return $class_name;
    }

    /**
     * Activate plugin.
     */
    public static function activate()
    {
        foreach ([
            'timber-library/timber.php' => 'Timber Library',
            // 'wp-timber-extended/wp-timber-extended.php' => 'WP Timber Extended',
        ] as $plugin => $name) {
            if (!is_plugin_active($plugin) && current_user_can('activate_plugins')) {
                wp_die(sprintf(
                    __('Sorry, but this plugin requires the %s plugin to be installed and active. <br><a href="%s">&laquo; Return to Plugins</a>', 'wp-hero'),
                    $name,
                    admin_url('plugins.php')
                ));
            }
        }
    }
}

if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

TimberExtended::get_instance();
