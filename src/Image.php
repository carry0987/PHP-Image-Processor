<?php
namespace carry0987\Image;

use carry0987\Image\ImageGD;
use carry0987\Image\ImageImagick;
use carry0987\Image\Exception\{
    IOException,
    UnsupportedLibraryException,
    InitializationException
};
use DateTimeImmutable;

//Handle image modification
class Image
{
    public $image;
    public $library = '';
    public $source_filepath = '';
    private $destination_filepath = null;
    //Inner variable
    private $config = array(
        'save_by_date' => false
    );
    private $param = array(
        'path_date' => null
    );

    const LIBRARY_GD = 'GD';
    const LIBRARY_IMAGICK = 'Imagick';

    public function __construct(string $source_filepath, string $library = null)
    {
        if (!file_exists($source_filepath)) {
            throw new IOException();
        }
        $this->source_filepath = $source_filepath;
        if (is_object($this->image)) return;
        $extension = strtolower(self::getExtension($source_filepath));
        if (!($this->library = self::getLibrary($library, $extension))) {
            throw new UnsupportedLibraryException();
        }
        $this->initLibrary($source_filepath);
    }

    //Unknow methods will be redirected to image object
    public function __call(string $method, array $arguments)
    {
        return call_user_func_array(array($this->image, $method), $arguments);
    }

    public function setAllowType(array | string $allow_type)
    {
        $this->image->setAllowType($allow_type);
    }

    public static function getExtension(string $filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    //Returns a normalized convolution kernel for sharpening
    public static function getSharpenMatrix(float $amount)
    {
        //Amount should be in the range of 48-10
        $amount = round(abs(-48 + ($amount * 0.38)), 2);
        $matrix = array(
            array(-1, -1, -1),
            array(-1, $amount, -1),
            array(-1, -1, -1)
        );
        $norm = array_sum(array_map('array_sum', $matrix));
        for ($i = 0; $i<3; $i++) {
            for ($j = 0; $j<3; $j++) {
                //$matrix[$i][$j] = $matrix[$i][$j] /  $norm;
                $matrix[$i][$j] /= $norm;
            }
        }

        return $matrix;
    }

    public static function isImagick()
    {
        return (extension_loaded('imagick') && class_exists('Imagick'));
    }

    public static function isGD()
    {
        return function_exists('gd_info');
    }

    public static function getLibrary(string $library, string $extension = null)
    {
        if (is_null($library)) {
            $library = self::LIBRARY_GD;
        }
        switch (strtolower($library)) {
            case 'auto':
            case self::LIBRARY_IMAGICK:
                if ($extension != 'gif' && self::isImagick()) {
                    return self::LIBRARY_IMAGICK;
                }
                break;
            case self::LIBRARY_GD:
                if (self::isGD()) {
                    return self::LIBRARY_GD;
                }
                break;
            default:
                if ($library != 'auto') {
                    return self::getLibrary('auto', $extension);
                }
                break;
        }

        return false;
    }

    public function saveByDate(int $timestamp = null): self
    {
        $this->config['save_by_date'] = true;
        if ($timestamp === null) {
            $timestamp = time();   
        }
        $date = new DateTimeImmutable('@'.$timestamp);
        $this->param['path_date'] = $date->format('Y/m/d/');

        return $this;
    }

    public function startProcess(): self
    {
        $this->image->startProcess();

        return $this;
    }

    public function setRootPath(string $root_path): self
    {
        $this->image->setRootPath($root_path);

        return $this;
    }

    public function setCompressionQuality(int $quality): self
    {
        $this->image->setCompressionQuality($quality);

        return $this;
    }

    public function getCreatedPath()
    {
        return $this->image->getCreatedPath();
    }

    public function getWidth()
    {
        return $this->image->getWidth();
    }

    public function getHeight()
    {
        return $this->image->getHeight();
    }

    public function cropImage(int $width, int $height, int $x, int $y)
    {
        return $this->image->cropImage($width, $height, $x, $y);
    }

    public function cropSquare(int $width)
    {
        return $this->image->cropSquare($width);
    }

    public function stripImage()
    {
        return $this->image->stripImage();
    }

    public function rotateImage(float $rotation)
    {
        return $this->image->rotateImage($rotation);
    }

    public function resizeImage(int $width, int $height)
    {
        return $this->image->resizeImage($width, $height);
    }

    public function sharpenImage(float $amount)
    {
        return $this->image->sharpenImage($amount);
    }

    public function compositeImage(Image $overlay, int $x, int $y, int $opacity)
    {
        return $this->image->compositeImage($overlay, $x, $y, $opacity);
    }

    public function writeImage(string $destination_filepath)
    {
        if (!empty($destination_filepath)) {
            if ($this->config['save_by_date'] === true) {
                $path_date = $this->param['path_date'] ?? '';
                $destination_filepath = $path_date.basename($destination_filepath);
            }
            $check_dir = dirname($destination_filepath);
            if (!is_dir($check_dir) || !file_exists($check_dir)) {
                mkdir($check_dir, 0755, true);
            }
        }

        // Write image
        $result = $this->image->writeImage($destination_filepath);
        $this->destination_filepath = $result ? $destination_filepath : null;

        return $result;
    }

    public function destroyImage() 
    {
        return $this->image->destroyImage();
    }

    private function initLibrary(string $source_filepath)
    {
        switch ($this->library) {
            case self::LIBRARY_IMAGICK:
                $this->image = new ImageImagick($source_filepath);
                break;
            case self::LIBRARY_GD:
                $this->image = new ImageGD($source_filepath);
                break;
            default:
                throw new InitializationException();
        }
    }
}
