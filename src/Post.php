<?php

namespace TimberExtended;

use Timber;
use TimberExtended;

class Post extends Timber\Post
{
    /** @var int The duration any template cache should stay fresh */
    public $cache_duration = DAY_IN_SECONDS;

    /** @var array Cache of related post queries */
    protected $cache_related_posts = [];

    /** @inheritdoc */
    public function __construct($pid = null)
    {
        parent::__construct($pid);

        // Disable transient caching if timber cache is disabled.
        if (!Timber::$cache) {
            $this->cache_duration = false;
        }

        $this->PostClass = TimberExtended::get_object_class('post', null, $this);
        $this->UserClass = TimberExtended::get_object_class('user', null, $this);
        $this->TermClass = TimberExtended::get_object_class('term', null, $this);
        $this->ImageClass = TimberExtended::get_object_class('image', null, $this);
    }

    /** @inheritdoc */
    public function author()
    {
        if (isset($this->post_author)) {
            // Use custom $UserClass.
            $this->author = new $this->UserClass($this->post_author);
            return $this->author;
        }
    }

    /** @inheritdoc */
    public function post_class($class = '')
    {
        // Disable expensive get_post_class() funciton call which we do not use.
        return $class;
    }

    /** @inheritdoc */
    public function thumbnail()
    {
        // Cache the generated image object.
        $this->thumbnail = parent::thumbnail();
        return $this->thumbnail;
    }

    /**
     * Get the ancestor of a post.
     *
     * @return Post
     */
    public function ancestor()
    {
        $ancestors = $this->ancestors;
        if (!empty($ancestors)) {
            return array_pop($ancestors);
        }
    }

    /**
     * Get the ancestors of the post.
     *
     * @return Post[]
     */
    public function ancestors()
    {
        $ancestors = get_post_ancestors($this->ID);
        $this->ancestors = Timber\PostGetter::get_posts($ancestors);
        return $this->ancestors;
    }

    /**
     * Get related posts.
     * @note queries are cached in different buckets as arguments might differ.
     *
     * @param int $posts_per_page
     * @param array $args Arguments to pass on to CRP if used.
     * @return Post[]
     */
    public function related_posts($posts_per_page = 3, $args = [])
    {
        $cid = $this->generate_cid('related', func_get_args());
        if (isset($this->cache_related_posts[$cid])) {
            return $this->cache_related_posts[$cid];
        }

        $post = $this;
        $this->cache_related_posts[$cid] = Timber\Helper::transient($cid, function () use ($post, $posts_per_page, $args) {
            if ($this->is_crp_active()) {
                return $post->get_related_by_crp($posts_per_page, $args);
            } else {
                return $post->get_related_by_terms($posts_per_page);
            }
        }, $this->cache_duration);

        return $this->cache_related_posts[$cid];
    }

    /**
     * Return related posts based on Contextual Related Posts result.
     *
     * @internal
     * @param int $posts_per_page
     * @param array $arg Additional arguments to pass to the query
     * @return Post[]
     */
    protected function get_related_by_crp($posts_per_page = 3, $args = [])
    {
        $related = get_crp_posts_id(array_merge($args, [
            'post_id' => $this->ID,
            'limit' => $posts_per_page,
        ]));

        if (empty($related)) {
            return [];
        }
        $related = wp_list_pluck($related, 'ID');
        return (new Timber\PostQuery($related))->get_posts();
    }

    /**
     * Return related posts based on terms.
     *
     * @internal
     * @param int $posts_per_page
     * @return Post[]
     */
    protected function get_related_by_terms($posts_per_page = 3)
    {
        global $wpdb;
        $terms = $this->terms();
        if (empty($terms)) {
            return (new Timber\PostQuery([
                'post_type' => $this->post_type,
                'post__not_in' => [$this->ID],
                'posts_per_page' => $posts_per_page,
                'ignore_sticky_posts' => true,
                'orderby' => 'rand',
            ]))->get_posts();
        }

        $tids = implode(',', array_column($terms, 'id'));
        $querystr = "
            SELECT      p.*, COUNT(t.term_id) AS score
            FROM        $wpdb->posts AS p
            INNER JOIN  $wpdb->term_relationships AS tr ON p.ID = tr.object_id
            INNER JOIN  $wpdb->terms AS t ON tr.term_taxonomy_id = t.term_id
            WHERE       p.post_type = '{$this->type}'
                        AND t.term_id IN ({$tids})
                        AND p.ID NOT IN ({$this->ID})
                        AND p.post_status = 'publish'
            GROUP BY    p.ID
            ORDER BY    score DESC
            LIMIT       $posts_per_page
        ";
        $posts = $wpdb->get_results($querystr, OBJECT);
        return (new Timber\PostQuery($posts))->get_posts();
    }

    /**
     * Return if post has Contextual Related Post functionality active.
     *
     * @internal
     * @return bool
     */
    protected function is_crp_active()
    {
        if (!function_exists('get_crp_posts_id')) {
            return false;
        }
        // @see https://github.com/WebberZone/contextual-related-posts/blob/ec1ec84df057dca5f1b61695fd450776cc181dbe/includes/content.php#L57
        global $crp_settings;
        if (!empty($crp_settings['exclude_on_post_types']) && strpos($crp_settings['exclude_on_post_types'], '=') === false) {
            $exclude_on_post_types = explode(',', $crp_settings['exclude_on_post_types']);
        } else {
            parse_str($crp_settings['exclude_on_post_types'], $exclude_on_post_types);
        }
        return !in_array($this->post_type, $exclude_on_post_types);
    }

    /**
     * Generate a unique cache id based on a prefix and arguments.
     *
     * @param string $prefix
     * @param mixed $args
     * @return string
     */
    protected function generate_cid($prefix, $args = [])
    {
        return $prefix . '_' . $this->ID . '_' . substr(md5(json_encode($args)), 0, 6);
    }

    /**
     * @deprecated
     */
    public function get_related($posts_per_page = 3, $args = [])
    {
        return $this->related_posts($posts_per_page, $args);
    }
}
