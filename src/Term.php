<?php

namespace TimberExtended;

use Timber;
use TimberExtended;

class Term extends Timber\Term
{
    public $ImageClass;
    public $thumbnail;
    public $ancestors;

    /** @inheritdoc */
    public function __construct($tid = null, $tax = '')
    {
        parent::__construct($tid, $tax);

        if ($this->PostClass === 'Timber\Post') {
            $this->PostClass = TimberExtended::get_object_class('post', null, $this);
        }
        if ($this->TermClass === 'Timber\Term') {
            $this->TermClass = TimberExtended::get_object_class('term', null, $this);
        }
        if (!isset($this->ImageClass) || $this->ImageClass === 'Timber\Image') {
            $this->ImageClass = TimberExtended::get_object_class('image', null, $this);
        }
    }

    /**
     * Helper to get a thumbnail field if it exists.
     *
     * @return Image
     */
    public function thumbnail()
    {
        foreach (['thumbnail_id', 'thumbnail'] as $field) {
            if ($thumbnail = $this->get_field($field)) {
                $this->thumbnail = new $this->ImageClass($thumbnail);
                return $this->thumbnail;
            }
        }
    }

    /**
     * Get the description of the term.
     *
     * @return string
     */
    public function content()
    {
        return apply_filters('the_content', $this->description);
    }

    /**
     * Get the ancestor of a term.
     *
     * @return Term
     */
    public function ancestor()
    {
        $ancestors = $this->ancestors;
        if (!empty($ancestors)) {
            return array_pop($ancestors);
        }
    }

    /**
     * Get the ancestors of the term.
     *
     * @return Term[]
     */
    public function ancestors()
    {
        $ancestors = get_ancestors($this->ID, $this->taxonomy);
        $this->ancestors = !empty($ancestors) ? Timber::get_terms($ancestors) : [];
        return $this->ancestors;
    }

}
