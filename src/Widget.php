<?php

namespace TimberExtended;

use Timber;
use WidgetOptionsExtended;

/**
 * A Timber Widget object for accessing ACF Widget content.
 *
 * @example
 * 1. Create a widget in Appearance > Add New Widgets
 *
 * 2. Create a PHP template file in your theme's template directory using the
 *    widgets slug, ie. widget-foobar.php
 *
 * 3. Add this content to it:
 *
 *    $context['widget'] = new TimberExtended\Widget($acfw);
 *    $context['widget']->init(get_defined_vars());
 *    Timber::render(['widget--' . $context['widget']->widget_id, 'widget.twig'], $context);
 *
 * 3. Disable the widget before and after wrappers for the sidebars.
 *
 *    register_sidebar([
 *      'name'          => __('Footer', 'theme-admin'),
 *      'id'            => 'sidebar-footer',
 *      'before_widget' => '',
 *      'after_widget'  => '',
 *      'before_title'  => '<h3>',
 *      'after_title'   => '</h3>'
 *    ]);
 *
 * 4. You can now theme the widget.
 *
 *    <section class="{{widget.class}}" id="{{widget.id}}">
 *      {{widget.section}}
 *    </section>
 *
 *  @todo try and get acf-widgets to apply widget_template_hierarchy and
 *  widget_template_include filters so we can hook in and automatically provide
 *  the twig template without having to create a PHP template in the theme.
 */
class Widget extends Timber\Core implements Timber\CoreInterface
{
    /** @inheritdoc */
    public $object_type = 'widget';
    /** @inheritdoc */
    public static $representation = 'widget';
    /** @var string $id Widget ID */
    public $id;
    /** @var array $classes Array of HTML classes */
    public $classes = [];
    /** @var string $class HTML classes */
    public $class = '';
    /** @var array $filterProperties Properties which are ignored during import */
    protected $filterProperties = [
        'args', 'context', 'name', 'id', 'params', 'instance', 'templates',
    ];

    /**
     * Create a Widget
     *
     * @param string $id
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->acfw = $id;
    }

    /**
     * Initialize a widget using variables.
     *
     * @param array $info Widget settings
     * @param bool $force
     */
    public function init($info, $force = false)
    {
        foreach (['id', 'name'] as $key) {
            if (isset($info[$key])) {
                $info['sidebar_' . $key] = $info[$key];
            }
        }

        if (!empty($info['params'][0])) {
            foreach (['id', 'name'] as $key) {
                if (isset($info['params'][0][$key])) {
                    $info['sidebar_' . $key] = $info['params'][0][$key];
                    unset($info['params'][0][$key]);
                }
            }
            $this->import($info['params'][0], $force);
        }

        // Might be ACFW related.
        if (isset($info['instance']) && isset($info['widget_id'])) {
            // Strip away widget-id suffixes from instance options.
            foreach ($info['instance'] as $key => $value) {
                if (mb_strpos($key, '-' . $info['widget_id']) !== false) {
                    $new_key = str_replace('-' . $info['widget_id'], '', $key);
                    $info['instance'][$new_key] = $info['instance'][$key];
                    unset($info['instance'][$key]);
                }
            }
            $this->import($info['instance'], $force);
        }

        // Don't shadow ACFW.
        if (empty($this->title)) {
            unset($this->title);
        }

        // Remove some properties
        $filter = array_combine($this->filterProperties, $this->filterProperties);
        $info = array_diff_key($info, $filter);
        $this->import($info, $force);

        // Add classes
        $this->add_class('widget');
        $this->add_class('widget--' . $this->widget_id);
        if ($this->widget_name) {
            $this->add_class('widget--' . strtolower(sanitize_html_class(str_replace(' ', '_', $this->widget_name))));
        }

        // Normalize Widgets Options settings.
        if (isset($this->{'extended_widget_opts-' . $this->widget_id})) {
            $this->extended_widget_opts = $this->{'extended_widget_opts-' . $this->widget_id};
        }

        // Add Widget Options classes using widget-options-extende.
        if (!empty($this->extended_widget_opts)) {
            $this->widget_options($this->extended_widget_opts);
        }
    }

    /**
     * Read setting from widget options plugin.
     *
     * @param array $options
     */
    protected function widget_options($options)
    {
        if (class_exists('WidgetOptionsExtended')) {
            $extra_classes = WidgetOptionsExtended::get_widget_classes($options);
            foreach ($extra_classes as $class) {
                $this->add_class($class);
            }
        }

        if (!empty($options['class']['title'])) {
            $this->hide_title = true;
        }
        if (!empty($options['class']['id'])) {
            $this->id = $options['class']['id'];
        }
    }

    /**
     * Add a CSS class.
     *
     * @param string $class_name
     */
    public function add_class($class_name)
    {
        $this->classes[] = $class_name;
        $this->class .= ' ' . $class_name;
    }

    /** @inheritdoc */
    public function meta($field_name)
    {
        return $this->get_meta_field($field_name);
    }

    /** @inheritdoc */
    public function get_meta_field($field_name)
    {
        if (!isset($this->$field_name)) {
            $field_value = $this->get_field($field_name);
            $this->$field_name = $field_value;
        }
        return $this->$field_name;
    }

    /** @inheritdoc */
    public function get_field($field_name, $id = null)
    {
        if (!isset($id)) {
            $id = $this->acfw;
        }
        return get_field($field_name, $id);
    }
}
