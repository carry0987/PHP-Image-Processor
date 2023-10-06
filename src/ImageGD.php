<?php
namespace carry0987\Image;

use carry0987\Image\Interface\ImageInterface;

//Class for GD
class ImageGD implements ImageInterface
{
    public $image;
    protected $file_info;
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
        $this->allow_type = array();
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
        $source_filepath = $this->source_filepath;
        $extension = strtolower(Image::getExtension($source_filepath));
        if ($extension === 'svg') throw new \Exception('[Image] Unsupported file format');
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $this->image = @imagecreatefromjpeg($source_filepath);
                if (!$this->image) {
                    $this->image = imagecreatefromstring(file_get_contents($source_filepath));
                }
                break;
            case 'png':
                $this->image = imagecreatefrompng($source_filepath);
                break;
            case 'gif':
                $this->image = imagecreatefromgif($source_filepath);
                break;
            case 'webp':
                $this->image = imagecreatefromwebp($source_filepath);
                break;
            default:
                $this->image = imagecreatefromstring(file_get_contents($source_filepath));
                break;
        }
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
        return imagesx($this->image);
    }

    public function getHeight()
    {
        return imagesy($this->image);
    }

    public function cropImage($width, $height, $x, $y)
    {
        $image_create = imagecreatetruecolor($width, $height);
        imagealphablending($image_create, false);
        imagesavealpha($image_create, true);
        if (function_exists('imageantialias')) {
            imageantialias($image_create, true);
        }
        $result = imagecopymerge($image_create, $this->image, 0, 0, $x, $y, $width, $height, 100);
        if ($result !== false) {
            imagedestroy($this->image);
            $this->image = $image_create;
            return true;
        }
        imagedestroy($image_create);
        return false;
    }

    public function cropSquare($size)
    {
        //Calculating the part of the image to use for thumbnail
        $width = $this->getWidth();
        $height = $this->getHeight();
        if ($width > $height) {
            $y = 0;
            $x = ($width - $height) / 2;
            $smallest_side = $height;
        } else {
            $x = 0;
            $y = ($height - $width) / 2;
            $smallest_side = $width;
        }
        $image_create = imagecreatetruecolor($size, $size);
        imagealphablending($image_create, false);
        imagesavealpha($image_create, true);
        if (function_exists('imageantialias')) {
            imageantialias($image_create, true);
        }
        $result = imagecopyresampled(
            $image_create,
            $this->image,
            0, 0, $x, $y,
            $size, $size,
            $smallest_side,
            $smallest_side
        );
        if ($result !== false) {
            imagedestroy($this->image);
            $this->image = $image_create;
        } else {
            imagedestroy($image_create);
        }
        return $result;
    }

    public function stripImage()
    {
        return true;
    }

    public function rotateImage($rotation)
    {
        $image_create = imagerotate($this->image, $rotation, 0);
        imagedestroy($this->image);
        $this->image = $image_create;
    }

    public function setCompressionQuality($quality)
    {
        $this->quality = $quality;
    }

    public function resizeImage(int $width, int $height)
    {
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
        $image_create = imagecreatetruecolor($width, $height);
        imagealphablending($image_create, false);
        imagesavealpha($image_create, true);
        if (function_exists('imageantialias')) {
            imageantialias($image_create, true);
        }
        $result = imagecopyresampled(
            $image_create,
            $this->image,
            0, 0, 0, 0,
            $width, $height,
            $image_width,
            $image_height
        );
        if ($result !== false) {
            imagedestroy($this->image);
            $this->image = $image_create;
        } else {
            imagedestroy($image_create);
        }
        return $result;
    }

    public function sharpenImage($amount)
    {
        $matrix = Image::getSharpenMatrix($amount);
        return imageconvolution($this->image, $matrix, 1, 0);
    }

    public function compositeImage($overlay, $x, $y, $opacity)
    {
        $ioverlay = $overlay->image->image;
        /*
        A replacement for php's imagecopymerge() function that supports the alpha channel
        See php bug #23815:  http://bugs.php.net/bug.php?id=23815
        */
        $ow = imagesx($ioverlay);
        $oh = imagesy($ioverlay);
        //Create a new blank image the site of our source image
        $cut = imagecreatetruecolor($ow, $oh);
        //Copy the blank image into the destination image where the source goes
        imagecopy($cut, $this->image, 0, 0, $x, $y, $ow, $oh);
        //Place the source image in the destination image
        imagecopy($cut, $ioverlay, 0, 0, 0, 0, $ow, $oh);
        imagecopymerge($this->image, $cut, $x, $y, 0, 0, $ow, $oh, $opacity);
        imagedestroy($cut);
        return true;
    }

    public function writeImage($destination_filepath)
    {
        $this->destination_filepath = $destination_filepath;
        $extension = strtolower(Image::getExtension($destination_filepath));
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($this->image, $destination_filepath, $this->quality);
                break;
            case 'png':
                imagepng($this->image, $destination_filepath, -1);
                break;
            case 'gif':
                imagegif($this->image, $destination_filepath);
                break;
            case 'webp':
                imagewebp($this->image, $destination_filepath, $this->quality);
                break;
            default:
                imagejpeg($this->image, $destination_filepath, $this->quality);
                break;
        }
    }

    public function destroyImage()
    {
        imagedestroy($this->image);
    }

    public function __destruct()
    {
        finfo_close($this->file_info);
    }
}
