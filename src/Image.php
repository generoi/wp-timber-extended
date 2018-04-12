<?php

namespace TimberExtended;

use Timber;

class Image extends Timber\Image
{
    /** @var bool $tojpg Force JPG output */
    public $tojpg = false;
    /** @var string $wrapper_class CSS class to wrap the image in */
    public $wrapper_class = 'responsive-embed';

    /** @var string $size Thumbnail size name */
    public $size;
    /** @var string $r_width Rendered width */
    public $r_width;
    /** @var string $r_width Rendered height */
    public $r_height;
    /** @var string $crop Crop position */
    public $crop;
    /** @var string $src_sizes Sizes attribute value */
    public $src_sizes;
    public $src_url_cache;

    /**
     * Render an image HTML element.
     *
     * @param int|string $width Rendered width of the image, or a thumbnail size name.
     * @param int $heigth Rendered height of the image, or null to avoid cropping.
     * @param string $crop Crop position
     * @param bool $tojpg Force JPG.
     * @return string
     */
    public function render($width = 'full', $height = null, $crop = 'default', $tojpg = null)
    {
        // Require a set of dimensions.
        if (!$this->width || !$this->height) {
            return $this->invalid_tag('no dimensions');
        }

        $this->set_dimensions($width, $height, $crop, $tojpg);

        $content = '<div class="' . $this->wrapper_class . '" style="padding-bottom: ' .  ($this->intrinsic_ratio() * 100) . '%;">';
        $content .= $this->tag;
        $content .= '</div>';

        return $content;
    }

    /**
     * Get/set the image tag.
     *
     * @param string $value If specified, set the HTML for the image tag.
     * @return string
     */
    public function tag($width = null, $height = null, $crop = 'default', $tojpg = null)
    {
        if (is_string($width) && !preg_match('/^[a-Z_\-0-9]$/', $width)) {
            $this->tag = $width;
            return;
        } else if (isset($width)) {
            $this->set_dimensions($width, $height, $crop, $tojpg);
        }

        $attributes['alt'] = $this->alt;
        $attributes['title'] = $this->title;
        $attributes['srcset'] = $this->srcset;

        if ($this->src_sizes) {
            $attributes['sizes'] = $this->src_sizes;
        }

        if ($this->has_smartcrop()) {
            $focus = unserialize($this->_wpsmartcrop_image_focus);
            $left = round($focus['left'], 2);
            $top = round($focus['top'], 2);
            $attributes['style'] = "object-position: $left% $top%;";
            $attributes['class'] = 'smartcrop';
        } else {
            $attributes['width'] = $this->r_width;
            $attributes['height'] = $this->r_height;
        }

        $attributes = $this->get_attribute_string($attributes);

        return "<img $attributes>";
    }

    /**
     * Get/set the srcset attribute.
     *
     * @param string $value If specified, set srcset value as a string
     * @return string
     */
    public function srcset($value = null)
    {
        if (!empty($value)) {
            $this->srcset = $value;
            return;
        }

        // If the full size was requested, render as is.
        if ($this->size && $this->size === 'full') {
            return $this->src;
        }

        $normal = $this->resize();
        $retina = $this->retina();

        $sources[] = $normal->src . ' ' . $normal->width . 'w';

        if ($retina->src && ($retina->src != $normal->src)) {
            $sources[] = $retina->src . ' 2x';
            $sources[] = $retina->src . ' ' . $retina->width .'w';
        }

        // If it's a larger image, provide a version in half it's size.
        if ($this->r_width > 400) {
            $half = $this->resize(round($this->r_width/2), round($this->r_height/2));

            if ($half->src != $normal->src) {
                $sources[] = $half->src . ' ' . $half->width .'w';
            }
        }

        return implode(', ', $sources);
    }

    /**
     * Set the source sizes attribute.
     *
     * @param string $value
     */
    public function src_sizes($value = null)
    {
        $this->src_sizes = $value;
    }

    /**
     * Set the rendered width of the image.
     *
     * @param int $value
     */
    public function r_width($value = null)
    {
        $this->r_width = $value;
    }

    /**
     * Set the rendered height of the image.
     *
     * @param int $value
     */
    public function r_height($value = null)
    {
        $this->r_height = $value;
    }

    /**
     * Set the crop position of the image.
     *
     * @param string $value
     */
    public function crop($value = null)
    {
        $this->crop = $value;
    }

    /**
     * Reinitialize the current image as a JPG.
     *
     * @return Image
     */
    public function tojpg()
    {
        $src = Timber\ImageHelper::img_to_jpg($this->src);
        $this->init($src);
        $this->src = $src;
        return $this;
    }

    /**
     * Get a second, resized version of the image.
     *
     * @param int $width
     * @param int $height
     * @return Image
     */
    public function resize($width = null, $height = null)
    {
        $width = isset($width) ? $width : $this->r_width;
        $height = isset($height) ? $height : $this->r_height;

        $src = Timber\ImageHelper::resize($this->src, $width, $height);
        return new static($src);
    }

    /**
     * Get a second, retina version of the image up to the size of the original
     * image.
     *
     * @param int $width
     * @param int $height
     * @return Image
     */
    public function retina($width = null, $height = null, $crop = null)
    {
        $width = isset($width) ? $width : $this->r_width;
        $height = isset($height) ? $height : $this->r_height;
        $crop = isset($crop) ? $crop : $this->crop;

        $width = $width * 2;
        $height = $height * 2;
        $max_width = $this->width;
        $max_height = $this->height;
        $aspect_ratio = $height / $width;
        if ($width > $max_width) {
            $width = $max_width;
            $height = $width * $aspect_ratio;
        }
        if ($height > $max_height) {
            $height = $max_height;
            $width = $height / $aspect_ratio;
        }
        $width = round($width);
        $height = round($height);

        $src = Timber\ImageHelper::resize($this->src, $width, $height, $crop);
        return new static($src);
    }

    /** @inheritdoc */
    public function src($size = 'full')
    {
        if (!empty($this->ID)) {
            $this->abs_url = null;
        }
        $this->src = parent::src($size);
        $this->abs_url = $this->src;

        // Force JPG conversion if set.
        if ($this->tojpg) {
            $this->tojpg();
        }
        // Sanitize spaces for "child" images.
        if (!$this->id) {
            $this->src = preg_replace('/\s+/', '%20', $this->src);
        }        
        return $this->src;
    }

    /** @inheritdoc */
    public function init($iid = false)
    {
        // Timber takes the ID regardless if it exists or not
        if ($iid instanceof self && empty($iid->ID)) {
            $iid = $iid->abs_url;
        }

        parent::init($iid);

        apply_filters('timber_extended/image/init', $this);
    }

    /**
     * Get the HTML string of a set of attributes expressed as an associative
     * array.
     *
     * @param array $attributes
     * @return string
     */
    protected function get_attribute_string($attributes)
    {
        $result = [];
        foreach ($attributes as $attribute => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $result[]  = "$attribute";
                }
            } elseif ($value) {
                $result[] = "$attribute=\"$value\"";
            }
        }
        return implode(' ', $result);
    }

    /**
     * Get an image tag for invalid images.
     * @return string
     */
    public function invalid_tag($message = '')
    {
        return '<img data-twig-error="' . $message . '">';
    }

    /** @inheritdoc */
    public function alt()
    {
        if ($alt = parent::alt()) {
            return $alt;
        }
        if ($alt = $this->title()) {
            return $alt;
        }
        return get_the_title();
    }

    /**
     * Get the intrinsic ratio of the rendered image.
     * @return float
     */
    public function intrinsic_ratio()
    {
        $w = intval($this->r_width);
        $h = intval($this->r_height);
        return $h / $w;
    }

    /**
     * Return if image has smartcrop enabled.
     * @return bool
     */
    public function has_smartcrop()
    {
        return !empty($this->_wpsmartcrop_enabled);
    }

    /**
     * Get the yoimg crop format for a size.
     *
     * @param string $size WordPress thumbnail size
     * @return array
     */
    public function crop_format($size)
    {
        if (!empty($this->yoimg_attachment_metadata)) {
            $metadata = $this->yoimg_attachment_metadata;
            if (!empty($metadata['crop'][$size])) {
                return $metadata['crop'][$size];
            }
        }
        return null;
    }

    /**
     * Get image dimensions speicified by the WordPress size.
     *
     * @param string $size WordPress thumbnail size
     * @return array
     */
    public static function get_image_dimensions($size)
    {
        global $_wp_additional_image_sizes;
        $sizes = [];
        foreach (get_intermediate_image_sizes() as $_size) {
            if (in_array($_size, ['thumbnail', 'medium', 'medium_large', 'large'])) {
                $sizes[$_size]['width']  = get_option("{$_size}_size_w");
                $sizes[$_size]['height'] = get_option("{$_size}_size_h");
                $sizes[$_size]['crop']   = get_option("{$_size}_crop");
            } elseif (isset($_wp_additional_image_sizes[$_size])) {
                $sizes[$_size] = [
                    'width'  => $_wp_additional_image_sizes[$_size]['width'],
                    'height' => $_wp_additional_image_sizes[$_size]['height'],
                    'crop'   => $_wp_additional_image_sizes[$_size]['crop'],
                ];
            }
        }
        if (isset($sizes[$size])) {
            return $sizes[$size];
        }
        return null;
    }

    public function set_dimensions($width = null, $height = null, $crop = 'default', $tojpg = null)
    {
        $this->crop = $crop;

        if (isset($tojpg)) {
            $this->tojpg = $tojpg;
        }

        if (is_string($width)) {
            // Thumbnail size name was passed.
            $this->size = $width;

            $crop_format = $this->crop_format($this->size);
            // If there's a replacement image for this breakpoint.
            if (isset($crop_format['replacement'])) {
                $this->init($crop_format['replacement']);
            }

            // YoImage crop
            if ($crop_format) {
                $op = new Image\Operation\Crop($crop_format['x'], $crop_format['y'], $crop_format['width'], $crop_format['height']);

                // Use the orignial image name in the filename rather than the
                // replacement.
                $au = Timber\ImageHelper::analyze_url($this->file_loc);
                $new_filename = $op->filename($au['filename'], $au['extension']);

                $source_path = $this->file_loc;
                $destination_path = Image\Helper::get_destination_path($this->file_loc, $new_filename);
                $destination_url = Image\Helper::get_destination_url($this->src, $new_filename);
                Image\Helper::operate($source_path, $destination_path, $op);
                $this->init($destination_url);
                $this->src = $destination_url;
            }

            if ($dimensions = self::get_image_dimensions($this->size)) {
                // Known size
                $this->r_width($dimensions['width']);

                if ($crop) {
                    $this->r_height($dimensions['height']);
                    $this->crop($dimensions['crop']);
                } else {
                    $this->r_height(round($this->height * ($this->r_width / $this->width)));
                }
            } else {
                // Unknown size (eg. full)
                $this->r_width($this->width);
                $this->r_height($this->height);
            }
        } elseif (isset($width) && isset($height)) {
            // Fixed size
            $this->r_width($width);
            $this->r_height($height);
        } elseif (isset($width) && !isset($height)) {
            // Fluid height.
            $this->r_width($width);
            $this->r_height(round($this->height * ($this->r_width / $this->width)));
        } else {
            // Unknown size
            $this->r_width($this->width);
            $this->r_height($this->height);
        }
    }
}
