<?php

namespace TimberExtended\Module;

class PasswordInheritance extends Module
{
    /** @inheritdoc */
    public function __construct()
    {
        parent::__construct();

        add_filter('timber/context', [$this, 'add_timber_context'], 1, 1);
    }

    /**
     * Attach variables to timber contexts.
     *
     * @param array $context
     */
    public function add_timber_context($context)
    {
        if (!empty($context['password_required']) || !is_page()) {
            return $context;
        }
        if (self::post_ancestor_password_required($context['post']->ID)) {
            $context['password_required'] = true;
        }
        return $context;
    }

    /**
     * Return if one of the post's ancestors requires a password
     *
     * @param int $pid
     * @return bool
     */
    public static function post_ancestor_password_required($pid = null)
    {
        $post = get_post($pid);
        if ($post->post_parent) {
            foreach (get_post_ancestors($pid) as $ancestor_id) {
                if (post_password_required($ancestor_id)) {
                    return true;
                }
            }
        }
        return false;
    }
}
