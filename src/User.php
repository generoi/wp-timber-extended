<?php

namespace TimberExtended;

use Timber;

class User extends Timber\User
{
    /** @inheritdoc */
    public function __construct($uid = false)
    {
        parent::__construct($uid);

        // Fix for Polylang/WPML
        $this->description = $this->get_meta_field('description');
    }

    /**
     * Get the content of the user.
     *
     * @return string
     */
    public function content()
    {
        return apply_filters('the_content', $this->description);
    }
}
