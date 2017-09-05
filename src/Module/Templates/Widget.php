<?php

namespace TimberExtended\Module\Templates;

use Timber;
use TimberExtended;

class Widget
{
    public function __construct()
    {
        add_filter('dynamic_sidebar_params', [__CLASS__, 'wrap_widget_output']);
    }

   /**
    * Replace the original sidebar display callback with our own wrapper
    * function `display_widget`.
    *
    * @param array $sidebar_params
    * @return array
    */
    public static function wrap_widget_output($sidebar_params)
    {
        if (is_admin()) {
            return $sidebar_params;
        }
        global $wp_registered_widgets;
        $current_widget_id = $sidebar_params[0]['widget_id'];
        $wp_registered_widgets[$current_widget_id]['original_callback'] = $wp_registered_widgets[$current_widget_id]['callback'];
        $wp_registered_widgets[$current_widget_id]['callback'] = [__CLASS__, 'display_widget'];
        return $sidebar_params;
    }

   /**
    * Render a widget using .
    *
    * @return string
    */
    public static function display_widget()
    {
        global $wp_registered_widgets;
        $original_callback_params = func_get_args();
        $widget_id   = $original_callback_params[0]['widget_id'];
        $widget_name = $original_callback_params[0]['widget_name'];
        $sidebar_id  = $original_callback_params[0]['id'];
        $original_callback = $wp_registered_widgets[$widget_id]['original_callback'];
        $wp_registered_widgets[$widget_id]['callback'] = $original_callback;

        if (is_callable($original_callback)) {
            $widget = $original_callback[0];

            ob_start();
            call_user_func_array($original_callback, $original_callback_params);
            if ($widget_output = ob_get_clean()) {
                $widget_output = self::widget_output($widget_output, $widget_id, $widget_name, $widget, $sidebar_id);
                echo apply_filters('widget_output', $widget_output, $widget_id, $widget_name, $widget, $sidebar_id);
            }
        }
    }

   /**
    * Render widget output with a timber template.
    *
    * @param string $output
    * @param string $widget_id
    * @param string $widget_name
    * @param WP_Widget $widget
    * @param string $sidebar_id
    * @return string
    */
    public static function widget_output($output, $widget_id, $widget_name, $widget, $sidebar_id)
    {
        // ACFW hardcodes this value when no template is found.
        if (strpos($output, 'No template found') !== false) {
            $output = null;
        }
        $settings = $widget->get_settings();
        $settings = isset($settings[$widget->number]) ? $settings[$widget->number] : $settings;

        $class_name = TimberExtended::get_object_class('widget', null, $widget);
        // @legacy
        $class_name = apply_filters('timber_extended/class_name', $class_name, ['widget'], $widget);

        $context['widget'] = new $class_name('widget_' . $widget_id);
        $context['widget']->init(array_merge($settings, [
            'widget_id' => $widget_id,
            'sidebar_id' => $sidebar_id,
            'content' => $output,
        ]));

        $templates = apply_filters('timber_extended/templates/suggestions', [
            'widgets/widget--' . $widget_id . '.twig',
            // @todo widget->slug?
            'widgets/widget--' . strtolower(sanitize_html_class(str_replace(' ', '_', $widget_name))) . '.twig',
            'widgets/widget--' . $sidebar_id . '.twig',
            'widgets/widget.twig',
        ]);

        $output = Timber::fetch($templates, $context);
        return $output;
    }
}
