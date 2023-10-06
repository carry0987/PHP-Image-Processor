<?php
namespace carry0987\Image;

use carry0987\Image\Interface\ImageInterface;

//Class for Imagick
class ImageImagick implements ImageInterface
{
    public $image;
    protected $file_info;
    private $source_filepath = null;
    private $allow_type = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'x-ms-bmp');
    private $quality = 75;
    private $root_path = null;
    private $destination_filepath;

    public function __construct($source_filepath)
    {
        $this->source_filepath = $source_filepath;
        $this->file_info = finfo_open(FILEINFO_MIME_TYPE);
    }

    public function setAllowType($allow_type)
    {
        if (!is_array($allow_type)) {
            $allow_type = explode(',', $allow_type);
        }
        foreach ($allow_type as $key => $value) {
            $this->allow_type[$key] = 'image/'.strtolower($value);
        }
    }

    public function checkFileType($filepath)
    {
        $mime_type = finfo_file($this->file_info, $filepath);
        $mime_type = str_replace('image/', '', $mime_type);
        if (!in_array($mime_type, $this->allow_type)) {
            throw new \Exception('[Image] Unsupported file format');
        }
    }

    public function startProcess()
    {
        if (is_object($this->image)) return;
        $this->checkFileType($this->source_filepath);
        $this->image = new \Imagick($this->source_filepath);
        $this->image->setImageDepth(8);
    }

    public function setRootPath($root_path)
    {
        $this->root_path = $root_path;
    }

    public function getCreateFilePath()
    {
        $destination_filepath = $this->destination_filepath;
        if ($this->root_path !== null) {
            $destination_filepath = str_replace($this->root_path, '', $this->destination_filepath);
        }
        return $destination_filepath;
    }

    public function getWidth()
    {
        return $this->image->getImageWidth();
    }

    public function getHeight()
    {
        return $this->image->getImageHeight();
    }

    public function setCompressionQuality($quality)
    {
        $this->quality = $quality;
    }

    public function cropImage($width, $height, $x, $y)
    {
        return $this->image->cropImage($width, $height, $x, $y);
    }

    public function cropSquare($width)
    {
        return $this->image->cropThumbnailImage($width, $width);
    }

    //Strips an image of all profiles and comments
    public function stripImage()
    {
        return $this->image->stripImage();
    }

    public function rotateImage($rotation)
    {
        $this->image->rotateImage(new \ImagickPixel(), -$rotation);
        $this->image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    public function resizeImage(int $width, int $height)
    {
        $this->image->setInterlaceScheme(\Imagick::INTERLACE_LINE);
        //Get image size
        $image_width = $this->getWidth();
        $image_height = $this->getHeight();
        //Check max width
        if ($width > $image_width) {
            $width = $image_width;
        }
        //Keep aspect ratio
        if ($height === 0) {
            $height = ceil(($width / $image_width) * $image_height);
        }
        /*
        if ($this->getWidth()%2 === 0 && $this->getHeight()%2 === 0 && $this->getWidth() > 3*$width) {
            $this->image->scaleImage($this->getWidth()/2, $this->getHeight()/2);
        }
        */
        return $this->image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
    }

    public function sharpenImage($amount)
    {
        $m = Image::getSharpenMatrix($amount);
        return $this->image->convolveImage($m);
    }

    public function compositeImage($overlay, $x, $y, $opacity)
    {
        if (!($overlay instanceof Image)) throw new \Exception('[Image] Invalid overlay image');
        $ioverlay = $overlay->image->image;
        /*
        if ($ioverlay->getImageAlphaChannel() !== Imagick::ALPHACHANNEL_OPAQUE) {
            //Force the image to have an alpha channel
            $ioverlay->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        }
        */
        if ($opacity < 100) {
            //NOTE: Using setImageOpacity will destroy current alpha channels
            $ioverlay->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity/100, \Imagick::CHANNEL_ALPHA);
        }
        return $this->image->compositeImage($ioverlay, \Imagick::COMPOSITE_DISSOLVE, $x, $y);
    }

    public function writeImage($destination_filepath)
    {
        $this->destination_filepath = $destination_filepath;
        //Set compress quality
        $this->image->setImageCompressionQuality($this->quality);
        //Save image color space
        $this->image->setImageColorspace($this->image->getImageColorspace());
        //Use 4:2:2 chroma subsampling (reduce file size by 20-30% with "almost" no human perception)
        $this->image->setSamplingFactors(array(2, 1));
        return $this->image->writeImage($destination_filepath);
    }

    public function destroyImage()
    {
        $this->image->clear();
    }

    public function __destruct()
    {
        finfo_close($this->file_info);
    }
}
