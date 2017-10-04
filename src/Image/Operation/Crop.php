<?php

namespace TimberExtended\Image\Operation;

use Timber;

class Crop extends Timber\Image\Operation
{
    /**
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     */
    public function __construct($x, $y, $width, $height)
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
    }

    /** @inheritdoc */
    public function filename($src_filename, $extension)
    {
        // We use a custom pattern which we clean out using a hook when the
        // original is deleted.
        $result = $src_filename . '-wp-hero-crop'
            . '-' . $this->width . 'x' . $this->height
            . '-' . $this->x . 'x' . $this->y;

        if ($extension) {
            $result .= '.' . $extension;
        }

        return $result;
    }

    /** @inheritdoc */
    public function run($source, $destination)
    {
        // @todo validation
        $image = wp_get_image_editor($source);
        if (!is_wp_error($image)) {
            $image->crop(
                $this->x,
                $this->y,
                // source size
                $this->width,
                $this->height,
                // target size
                $this->width,
                $this->height
            );
            $result = $image->save($destination);
            if (is_wp_error($result)) {
                Timber\Helper::error_log('Error cropping image (wp-hero)');
                Timber\Helper::error_log($result);
            } else {
                return true;
            }
        } elseif (isset($image->error_data['error_loading_image'])) {
            Timber\Helper::error_log('Error loading (wp-hero) ' . $image->error_data['error_loading_image']);
        } else {
            Timber\Helper::error_log($image);
        }
        return false;
    }
}

