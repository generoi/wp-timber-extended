<?php

namespace TimberExtended\Module;

use Timber;
use TimberExtended;
use Twig_SimpleFilter;
use Twig_Extension_StringLoader;
use WP_Query;

class TwigExtensions extends Module
{
    /** @inheritdoc */
    public function __construct()
    {
        parent::__construct();

        if ($this->has_theme_feature('core')) {
            add_filter('timber/twig', [$this, 'add_core']);
        }
        if ($this->has_theme_feature('contrib')) {
            add_filter('timber/twig', [$this, 'add_contrib']);
        }
        if ($this->has_theme_feature('functional')) {
            add_filter('timber/twig', [$this, 'add_functional']);
        }
    }

    /**
     * Add core twig helpers.
     *
     * @param Twig_Environment $twig
     * @return Twig_Environment
     */
    public function add_core($twig)
    {
        // Add support for `template_from_string`.
        $twig->addExtension(new Twig_Extension_StringLoader());
        // Add `linkify` filter.
        $twig->addExtension(new TwigExtensions\Linkify());

        $twig->addFunction(new Timber\Twig_Function('TPost', [$this, 'fn_post']));
        $twig->addFunction(new Timber\Twig_Function('TTerm', [$this, 'fn_term']));
        $twig->addFunction(new Timber\Twig_Function('TImage', [$this, 'fn_image']));
        $twig->addFunction(new Timber\Twig_Function('TUser', [$this, 'fn_user']));

        $twig->addFunction(new Timber\Twig_Function('get_posts', [$this, 'fn_get_posts']));
        $twig->addFunction(new Timber\Twig_Function('get_paged', [$this, 'fn_get_paged']));
        $twig->addFunction(new Timber\Twig_Function('get_terms', [$this, 'fn_get_terms']));
        $twig->addFilter(new Twig_SimpleFilter('datauri', [$this, 'filter_datauri']));
        $twig->addFunction(new Timber\Twig_Function('ob_function', [$this, 'fn_ob_function']));
        $twig->addFilter(new Twig_SimpleFilter('the_content', [$this, 'filter_the_content']));
        $twig->addFilter(new Twig_SimpleFilter('wptrim', [$this, 'filter_wptrim']));
        $twig->addFunction(new Timber\Twig_Function('current_url', [$this, 'fn_current_url']));
        $twig->addFilter(new Twig_SimpleFilter('has_term', [$this, 'filter_has_term']));

        return $twig;
    }

    /**
     * Add contrib twig helpers.
     *
     * @param Twig_Environment $twig
     * @return Twig_Environment
     */
    public function add_contrib($twig)
    {
        $twig->addFunction(new Timber\Twig_Function('facetwp_display', [$this, 'fn_facetwp_display']));
        $twig->addFunction(new Timber\Twig_Function('d', [$this, 'fn_d']));
        $twig->addFilter(new Twig_SimpleFilter('d', [$this, 'fn_d']));
        $twig->addFunction(new Timber\Twig_Function('wpml_url', [$this, 'fn_wpml_url']));

        // Polylang integration
        $twig->addFunction(new Timber\Twig_Function('pll__', function ($string) {
            return pll__($string);
        }));
        $twig->addFunction(new Timber\Twig_Function('pll_e', function ($string) {
            return pll_e($string);
        }));

        return $twig;
    }

    /**
     * Add functional twig helpers.
     *
     * @param Twig_Environment $twig
     * @return Twig_Environment
     */
    public function add_functional($twig)
    {
        $twig->addFilter(new Twig_SimpleFilter('filter', [$this, 'filter_filter']));
        $twig->addFilter(new Twig_SimpleFilter('obj_merge', [$this, 'filter_obj_merge']));
        $twig->addFilter(new Twig_SimpleFilter('map', [$this, 'filter_map']));
        $twig->addFilter(new Twig_SimpleFilter('values', [$this, 'filter_values']));
        $twig->addFilter(new Twig_SimpleFilter('keys', [$this, 'filter_keys']));
        $twig->addFilter(new Twig_SimpleFilter('group_by_term', [$this, 'filter_group_by_term']));
        $twig->addFilter(new Twig_SimpleFilter('tel', [$this, 'filter_tel']));
        $twig->addFilter(new Twig_SimpleFilter('bool', 'boolval'));
        $twig->addFilter(new Twig_SimpleFilter('int', 'intval'));

        return $twig;
    }

    // Core
    // -----------------------------------------------------------------------
    //

    /**
     * Get a timber post object.
     *
     * @param int|object $pid
     * @param string $post_class Class to create or post type.
     * @return Post
     */
    public function fn_post($pid, $post_class = null)
    {
        static $post_types;

        if ($post_class) {
            if (!isset($post_types)) {
                $post_types = wp_list_pluck(get_post_types([], 'objects'), 'name');
            }
            if (isset($post_types[$post_class])) {
                $post_type = $post_types[$post_class];
                $post_class_map = apply_filters('Timber\PostClassMap', $post_class);
                if (is_array($post_class_map) && isset($post_class_map[$post_type])) {
                    $post_class = $post_class_map[$post_type];
                }
            }
        }

        return $this->object_getter('post', $pid, $post_class);
    }

    /**
     * Get a timber term object.
     *
     * @param int|object $tid
     * @param string $term_class
     * @return Term
     */
    public function fn_term($tid, $term_class = null)
    {
        return $this->object_getter('term', $tid, $term_class);
    }

    /**
     * Get a timber image object.
     *
     * @param int|object $tid
     * @param string $term_class
     * @return Term
     */
    public function fn_image($iid, $image_class = null)
    {
        return $this->object_getter('image', $iid, $image_class);
    }

    /**
     * Get a timber user object.
     *
     * @param int|object $tid
     * @param string $term_class
     * @return Term
     */
    public function fn_user($uid, $user_class = null)
    {
        return $this->object_getter('user', $uid, $user_class);
    }

    /**
     * Get a collection of posts including their pager.
     *
     * @param mixed $options Post type as a string, a WP_Query object or an
     * array of options.
     * @param string $post_class
     * @return Timber\PostQuery
     *
     * @example {% set posts = get_posts({'post_type': 'page'}) %}
     */
    public function fn_get_posts($options = null, $post_class = null, $return_collection = false)
    {
        if (!($options instanceof WP_Query)) {
            if (is_string($options)) {
                $post_type = $options;
                $options = [];
                $options['post_type'] = $post_type;
            } else {
                $options = $this->toArray($options);
            }
        }
        if (!isset($post_class)) {
            $post_class = TimberExtended::get_object_class('post');
        }
        return new Timber\PostQuery($options, $post_class);
    }

    /**
     * Get the page number.
     *
     * @return int
     */
    public function fn_get_paged()
    {
        return get_query_var('paged') ? get_query_var('paged') : 1;
    }

    /**
     * Get terms.
     *
     * @param string|array $category Category name or the query options.
     * @param array $options Query options.
     * @param string $term_class
     * @return Term[]
     *
     * @example {% set posts = get_terms('category_name', {'parent': 0}) %}
     */
    public function fn_get_terms($category, $options = null, $term_class = null)
    {
        if (!isset($term_class)) {
            $term_class = TimberExtended::get_object_class('term');
        }
        return Timber::get_terms($category, $this->toArray($options), $term_class);
    }

    /**
     * Get the datauri of a file URL.
     *
     * @param string $image_url
     * @return string
     *
     * @example {{thumbnail.src|resize(50)|datauri}}
     */
    public function filter_datauri($image_url)
    {
        $cid = 'datauri_' . substr(md5($image_url), 0, 6);
        if (!($datauri = get_transient($cid))) {
            $image_path = Timber\ImageHelper::get_server_location($image_url);
            if (!file_exists($image_path)) {
                return 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
            }
            $base64 = base64_encode(file_get_contents($image_path));
            $mime = mime_content_type($image_path);
            $datauri = "data:$mime;base64,$base64";
            set_transient($cid, $datauri);
        }
        return $datauri;
    }

    /**
     * Call a function and return it's printed content.
     *
     * @param string $fn Function name
     * @param mixed $args,... Arguments used to call the function
     * @return string
     *
     * @example {{ob_function('woocommerce_template_single_price')}}
     */
    public function fn_ob_function($fn, ...$args)
    {
        return Timber\Helper::ob_function($fn, $args);
    }

    /**
     * Apply the_content filter to text.
     *
     * @param string $content
     * @return string
     *
     * @example {{ post.foobar|the_content }}
     */
    public function filter_the_content($content = '')
    {
        return apply_filters('the_content', $content);
    }

    /**
     * Strip all leading and trailing whitespace, newlines as well as html
     * entity codes.
     *
     * @param string $content
     * @return string
     *
     * @example {{ post.get_preview()|wp_trim }}
     */
    public function filter_wptrim($content = '')
    {
        // @see https://stackoverflow.com/a/22004695/319855
        $whitespace = '<br\s*/?>|\s|&nbsp;|<p>&nbsp;</p>';
        $content = preg_replace('#^(' . $whitespace . ')*(.+?)(' . $whitespace . ')*$#mi', '$2', $content);
        return trim($content);
    }

    /**
     * Get the absolute URL of the current page.
     *
     * @return string
     */
    public function fn_current_url()
    {
        global $wp;
        return home_url(add_query_arg([], $wp->request));
    }

    /**
     * Filter a list of posts (or one) by having a term.
     *
     * @param mixed $array
     * @param string|int|array $term
     * @param string $taxonomy
     * @return bool
     *
     * @todo inefficient.
     * @example {% if post|has_term('project-2') %}
     */
    public function filter_has_term($array, $term, $taxonomy = '')
    {
        if (is_object($array)) {
            return has_term($term, $taxonomy, $array);
        }
        // @todo optimize.
        return array_filter($array, function ($item) use ($term, $taxonomy) {
            return has_term($term, $taxonomy, $item);
        });
    }

    // Functional
    // -----------------------------------------------------------------------

    /**
     * Group a set of posts by terms in the taxonomy.
     * @todo inefficient
     *
     * @param array $array Posts to group
     * @param string $taxonomy The taxonomy to group by
     * @return array
     *
     * @example {{posts|group_by_term('category')}}
     */
    public function filter_group_by_term($array, $taxonomy)
    {
        $groups = [];
        // Iterate over posts.
        foreach ($array as $item) {
            // Get all post terms in specified taxonomy.
            // @todo optimize.
            $terms = get_the_terms($item, $taxonomy);
            if (!$terms) {
                continue;
            }

            // Group posts by term.
            foreach ($terms as $term) {
                $class_name = TimberExtended::get_object_class('term', null, $term);
                if (!isset($groups[$term->term_id])) {
                    $groups[$term->term_id] = new \stdClass();
                    $groups[$term->term_id]->term = new $class_name($term);
                    $groups[$term->term_id]->posts = [];
                    $groups[$term->term_id]->children = [];
                    $groups[$term->term_id]->parents = [];
                }
                $groups[$term->term_id]->posts[] = $item;
            }

            // Iterate over the groups and attach children and parents ids.
            foreach ($groups as $term_id => $group) {
                $parent_id = $group->term->parent;

                if ($parent_id != 0) {
                    $parent_id = (string) $parent_id;

                    if (isset($groups[$parent_id])) {
                        // Attach each child term to the parent.
                        $groups[$parent_id]->children[] = $term_id;

                        if (!in_array($parent_id, $group->parents)) {
                            $group->parents[] = $parent_id;

                            // If there's a grandparent attach that them too.
                            if (!empty($groups[$parent_id])) {
                                $grandparent_id = $groups[$parent_id]->term->parent;

                                if ($grandparent_id && !in_array($grandparent_id, $group->parents)) {
                                    $group->parents[] = $grandparent_id;
                                }
                            }
                        }
                    }
                }
            }
        }

        uasort($groups, function ($a, $b) {
            $a_order = isset($a->term->term_order) ? $a->term->term_order : 100;
            $b_order = isset($b->term->term_order) ? $b->term->term_order : 100;

            if ($a_order == $b_order) {
                return 0;
            }
            return ($a_order < $b_order) ? -1 : 1;
        });

        return $groups;
    }

    /**
     * Get all the keys of a list.
     *
     * @param array $array
     * @return array
     *
     * @example {{ block_grid|keys|join(' ') }}
     */
    public function filter_keys($array)
    {
        if (is_array($array)) {
            return array_keys($array);
        }
        return [];
    }

    /**
     * Get all the values of a list.
     *
     * @param array $array
     * @return array
     *
     * @example {{ block_grid|values|join(' ') }}
     */
    public function filter_values($array)
    {
        if (is_array($array)) {
            return array_values($array);
        }
        return [];
    }

    /**
     * Apply a function to all items in a list.
     *
     * @param array $array
     * @param string $function
     * @param mixed $args,...
     * @return array
     *
     * @example {{ post.organizers|map('intval') }}
     */
    public function filter_map($array, $function, ...$args)
    {
        if (!is_array($array)) {
            $array = [$array];
        }
        return array_map($function, $array, $args);
    }

    /**
     * A version of merge that works with objects.
     *
     * @param array|object $array Object to return together with merged object
     * @param array|object $value Object/array to merge into the source
     * @return mixed
     *
     * @example {{ widget|merge(widget.section|default({})) }}
     */
    public function filter_obj_merge($array, $value)
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_object($array)) {
            foreach ($value as $key => $val) {
                $array->$key = $val;
            }
        } elseif (is_array($array)) {
            $array = array_merge($array, $value);
        }

        return $array;
    }

    /**
     * Filter a list of objects or arrays.
     *
     * @param array $array List to filter
     * @param string|null $key If null, filter out falsey items from list
     * @param mixed $value Value to compare
     * @return array
     *
     * @example {{ posts|filter('post_type', 'product') }}
     * @example {{ menu_items|filter('enabled') }}
     * @example {{ items|filter }}
     */
    public function filter_filter($array, $key = null, $value = null)
    {
        if (!is_array($array)) {
            return [];
        }
        if (is_null($key)) {
            return array_filter($array);
        }
        return array_filter($array, function ($item) use ($key, $value) {
            // Filter by value.
            if (isset($value)) {
                if (is_object($item)) {
                    return $item->$key === $value;
                } elseif (is_array($item)) {
                    return $item[$key] === $value;
                }
                return false;
            } // Filter by key existance.
            else {
                if (is_object($item)) {
                    return isset($item->$key);
                } elseif (is_array($item)) {
                    // Sequential array.
                    if (array_values($item) === $item) {
                        return in_array($key, $item);
                    } // Associative array.
                    else {
                        return isset($item[$key]);
                    }
                }
                return $item === $key;
            }
        });
    }

    /**
     * Strip everything except for numbers making it a valid tel: value.
     *
     * @param string $number
     * @return string
     *
     * @example <a href="tel:{{post.phone|tel}}">
     */
    public function filter_tel($number)
    {
        return preg_replace('/[^0-9]/', '', $number);
    }

    // Contrib
    // -----------------------------------------------------------------------

    /**
     * Get a FacetWP display.
     *
     * @param $args,... All arguments are passes as is
     *
     * @example {{ facetwp_display('facet', 'house_type') }}
     */
    public function fn_facetwp_display(...$args)
    {
        return facetwp_display(...$args);
    }

    /**
     * Call the kint debugger
     *
     * @param $args,... All arguments are passes as is
     *
     * @example {{ posts|d }}
     * @example {{ d(posts) }}
     */
    public function fn_d(...$args)
    {
        return d(...$args);
    }

    /**
     * Return a WPML translated object permalink
     *
     * @param string|int $id Object ID or the page slug.
     * @param string $type Object type
     * @param bool $return
     * @param string $langcode
     * @return string
     *
     * @example {{ wpml_url('contact', 'page') }}
     */
    public function fn_wpml_url($id, $type = 'page', $return = false, $langcode = null)
    {
        if (is_string($id) && !is_numeric($id)) {
            switch ($type) {
                case 'page':
                    $id = get_page_by_path($id);
                    break;
            }
        }
        $id = apply_filters('wpml_object_id', $id, $type, $return, $langcode);
        return get_permalink($id);
    }

    // Utils
    // -----------------------------------------------------------------------

    /**
     * Convert an object to an array if possible.
     *
     * @param mixed $options
     * @return mixed
     */
    protected function toArray($options = null)
    {
        if (isset($options) && is_object($options)) {
            $options = json_decode(json_encode($options), true);
        }
        return $options;
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
    protected function object_getter($type, $object, $class_name = null)
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
                        $obj = $this->object_create($type, $obj, $class_name);
                    } else {
                        $obj = new $class_name($obj);
                    }
                }
            } else {
                foreach ($object as &$obj) {
                    if ($is_guess_class_name) {
                        $obj = $this->object_create($type, $obj, $class_name);
                    } else {
                        $obj = new $class_name($obj);
                    }
                }
            }
            return $object;
        }
        if ($is_guess_class_name) {
            $obj = $this->object_create($type, $object, $class_name);
        } else {
            $obj = new $class_name($object);
        }
        return $obj;
    }

    /**
     * Create a Timber object using the correct Timber class.
     *
     * @param string $type Object type (post, term, image, etc)
     * @param mixed $object Object or list of objects
     * @param string $class_name Class to create objects with
     * @return mixed
     */
    protected function object_create($type, $object, $class_name)
    {
        $object = new $class_name($object);
        // Verify that the class is correct
        $object_class_name = TimberExtended::get_object_class($type, null, $object);
        if ($class_name !== $object_class_name) {
            $object = new $object_class_name($object);
        }
        return $object;
    }
}
