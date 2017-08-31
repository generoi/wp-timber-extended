<?php

namespace TimberExtended;

use Timber;
use TimberExtended;

class Term extends Timber\Term
{
    /** @inheritdoc */
    public function __construct($tid = null, $tax = '')
    {
        parent::__construct($tid, $tax);

        $this->PostClass = TimberExtended::get_object_class('post', null, $this);
        $this->TermClass = TimberExtended::get_object_class('term', null, $this);
        $this->ImageClass = TimberExtended::get_object_class('image', null, $this);
    }

    /**
     * Helper to get a thumbnail field if it exists.
     *
     * @return Image
     */
    public function thumbnail() {
        if ($thumbnail = $this->get_field('thumbnail')) {
            $this->thumbnail = new $this->ImageClass($thumbnail);
            return $this->thumbnail;
        }
    }
}
