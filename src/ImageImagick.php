<?php
namespace carry0987\Image;

use carry0987\Image\Interface\ImageInterface;
use Imagick;
use ImagickPixel;

//Class for Imagick
class ImageImagick implements ImageInterface
{
    public $image;
    /**
     * @var \finfo A fileinfo resource.
     */
    protected $file_info;
    private $allow_type = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'x-ms-bmp', 'avif');
    private $quality = 75;
    private $root_path = null;
    private $source_filepath = null;
    private $destination_filepath;
    private $is_animated = false;

    public function __construct(string $source_filepath)
    {
        $this->file_info = finfo_open(FILEINFO_MIME_TYPE);
        $this->source_filepath = $source_filepath;
        $this->allow_type = array_map(function($type) {
            return 'image/'.strtolower(trim($type));
        }, $this->allow_type);

        if ($this->file_info === false) {
            throw new \Exception('[Image] Unable to open fileinfo resource.');
        }
    }

    public function setAllowType(array $allow_type): self
    {
        $this->allow_type = $allow_type;

        return $this;
    }

    public function checkFileType(string $filepath): self
    {
        $mime_type = finfo_file($this->file_info, $filepath);
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
        $this->image = new Imagick($this->source_filepath);
        // Check if GIF for processing animation
        if ($this->image->getNumberImages() > 1) {
            $this->is_animated = true;
            // Prepare for animation handling
            $this->image = $this->image->coalesceImages();
        }
        $this->correctImageOrientation();
        $this->image->setImageDepth(8);

        return $this;
    }

    public function getRootPath(): ?string
    {
        return $this->root_path;
    }

    public function getCreatedPath(bool $full_path = false): ?string
    {
        $destination_filepath = $this->destination_filepath;
        if ($this->root_path !== null && $full_path === false) {
            $destination_filepath = str_replace($this->root_path, '', $this->destination_filepath);
        }

        return $destination_filepath;
    }

    public function getWidth(): int
    {
        return $this->image->getImageWidth();
    }

    public function getHeight(): int
    {
        return $this->image->getImageHeight();
    }

    public function cropImage(int $width, int $height, int $x, int $y): bool
    {
        if ($this->is_animated) {
            $result = false;
            foreach ($this->image as $frame) {
                $result = $frame->cropImage($width, $height, $x, $y);
                if (!$result) break;
            }
            return $result;
        }

        return $this->image->cropImage($width, $height, $x, $y);
    }

    public function cropSquare(int $width): bool {
        if ($this->is_animated) {
            $result = false;
            foreach ($this->image as $frame) {
                $result = $frame->cropThumbnailImage($width, $width);
                if (!$result) break;
            }
            return $result;
        }

        return $this->image->cropThumbnailImage($width, $width);
    }

    //Strips an image of all profiles and comments
    public function stripImage(): bool
    {
        return $this->image->stripImage();
    }

    public function rotateImage(float $rotation): void
    {
        if ($this->is_animated) {
            foreach ($this->image as $frame) {
                $frame->rotateImage(new ImagickPixel(), -$rotation);
                $frame->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            }
            return;
        }
        $this->image->rotateImage(new ImagickPixel(), -$rotation);
        $this->image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    public function resizeImage(int $width, int $height): bool
    {
        $this->image->setInterlaceScheme(Imagick::INTERLACE_LINE);
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
        if ($this->is_animated) {
            $result = false;
            foreach ($this->image as $frame) {
                $frame->setInterlaceScheme(Imagick::INTERLACE_LINE);
                $result = $frame->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
                if (!$result) break;
            }
            return $result;
        }

        return $this->image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
    }

    public function sharpenImage(float $amount): bool
    {
        $m = Image::getSharpenMatrix($amount);
        if ($this->is_animated) {
            $result = false;
            foreach ($this->image as $frame) {
                $result = $frame->convolveImage($m);
                if (!$result) break;
            }
            return $result;
        }

        return $this->image->convolveImage($m);
    }

    public function compositeImage(Image $overlay, int $x, int $y, int $opacity): bool
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
            $ioverlay->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity/100, Imagick::CHANNEL_ALPHA);
        }

        return $this->image->compositeImage($ioverlay, Imagick::COMPOSITE_DISSOLVE, $x, $y);
    }

    public function correctImageOrientation(): self
    {
        $orientation = $this->image->getImageOrientation();

        switch ($orientation) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $this->image->rotateimage(new ImagickPixel('none'), 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $this->image->rotateimage(new ImagickPixel('none'), 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $this->image->rotateimage(new ImagickPixel('none'), -90);
                break;
        }

        // Set the orientation to the default value if there was a change
        if ($orientation !== Imagick::ORIENTATION_TOPLEFT) {
            $this->image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        }

        return $this;
    }

    public function writeImage(string $destination_filepath): bool
    {
        $this->destination_filepath = $destination_filepath;
        //Set compress quality
        $this->image->setImageCompressionQuality($this->quality);
        //Save image color space
        $this->image->setImageColorspace($this->image->getImageColorspace());
        //Use 4:2:2 chroma subsampling (reduce file size by 20-30% with "almost" no human perception)
        $this->image->setSamplingFactors(array(2, 1));

        if ($this->is_animated) {
            $this->image = $this->image->deconstructImages();
            $result = $this->image->writeImages($destination_filepath, true);
        } else {
            $result = $this->image->writeImage($destination_filepath);
        }

        return $result;
    }

    public function destroyImage(): bool
    {
        return $this->image->clear();
    }

    public function __destruct()
    {
        finfo_close($this->file_info);
    }
}
