<?php
namespace carry0987\Image;

use carry0987\Image\Interface\ImageInterface;

//Class for GD
class ImageGD implements ImageInterface
{
    public $image;
    /**
     * @var \finfo A fileinfo resource.
     */
    protected $file_info;
    private $allow_type = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'x-ms-bmp');
    private $quality = 75;
    private $root_path = null;
    private $destination_filepath;

    public function __construct(string $source_filepath)
    {
        $this->source_filepath = $source_filepath;
        $this->file_info = finfo_open(FILEINFO_MIME_TYPE);

        if ($this->file_info === false) {
            throw new \Exception('[Image] Unable to open fileinfo resource.');
        }
    }

    public function setAllowType(array|string $allow_type): self
    {
        if (!is_array($allow_type)) {
            $allow_type = explode(',', $allow_type);
        }
        $this->allow_type = array_map(function($type) {
            return 'image/'.strtolower(trim($type));
        }, $allow_type);

        return $this;
    }

    public function checkFileType(string $filepath): self
    {
        $mime_type = finfo_file($this->file_info, $filepath);
        $mime_type = str_replace('image/', '', $mime_type);
        if (!in_array($mime_type, $this->allow_type)) {
            throw new \Exception('[Image] Unsupported file format');
        }

        return $this;
    }

    public function setRootPath(string $root_path): self
    {
        $this->root_path = $root_path;

        return $this;
    }

    public function setCompressionQuality(int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    public function startProcess(): self
    {
        if (is_object($this->image)) return $this;
        $this->checkFileType($this->source_filepath);
        $source_filepath = $this->source_filepath;
        $extension = strtolower(Image::getExtension($source_filepath));
        if ($extension === 'svg') throw new \Exception('[Image] Unsupported file format');
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $this->image = @imagecreatefromjpeg($source_filepath);
                $this->correctImageOrientation();
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

        return $this;
    }

    public function getCreatedPath()
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

    public function cropImage(int $width, int $height, int $x, int $y)
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

    public function cropSquare(int $size)
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

    public function rotateImage(float $rotation)
    {
        $image_create = imagerotate($this->image, $rotation, 0);
        imagedestroy($this->image);
        $this->image = $image_create;
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

    public function sharpenImage(float $amount)
    {
        $matrix = Image::getSharpenMatrix($amount);

        return imageconvolution($this->image, $matrix, 1, 0);
    }

    public function compositeImage(Image $overlay, int $x, int $y, int $opacity)
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

    public function correctImageOrientation(): self
    {
        if (!function_exists('exif_read_data')) return $this;
        $exif = @exif_read_data($this->source_filepath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $this->image = imagerotate($this->image, 180, 0);
                    break;
                case 6:
                    $this->image = imagerotate($this->image, -90, 0);
                    break;
                case 8:
                    $this->image = imagerotate($this->image, 90, 0);
                    break;
            }
        }

        return $this;
    }

    public function writeImage(string $destination_filepath)
    {
        $this->destination_filepath = $destination_filepath;
        $extension = strtolower(Image::getExtension($destination_filepath));
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $result = imagejpeg($this->image, $destination_filepath, $this->quality);
                break;
            case 'png':
                $result = imagepng($this->image, $destination_filepath, -1);
                break;
            case 'gif':
                $result = imagegif($this->image, $destination_filepath);
                break;
            case 'webp':
                $result = imagewebp($this->image, $destination_filepath, $this->quality);
                break;
            default:
                $result = imagejpeg($this->image, $destination_filepath, $this->quality);
                break;
        }

        return $result;
    }

    public function destroyImage(): bool
    {
        return imagedestroy($this->image);
    }

    public function __destruct()
    {
        finfo_close($this->file_info);
    }
}
