<?php
namespace carry0987\Image;

use carry0987\Image\ImageGD;
use carry0987\Image\ImageImagick;

//Handle image modification
class Image
{
    public $image;
    public $library = '';
    public $source_filepath = '';
    //Inner variable
    private $config = array(
        'save_by_date' => false
    );
    private $param = array();

    const LIBRARY_GD = 'GD';
    const LIBRARY_IMAGICK = 'Imagick';

    public function __construct(string $source_filepath, $library = null)
    {
        if (!file_exists($source_filepath)) {
            throw new \Exception('File does not exist');
        }
        $this->source_filepath = $source_filepath;
        if (is_object($this->image)) return;
        $extension = strtolower(self::getExtension($source_filepath));
        if (!($this->library = self::getLibrary($library, $extension))) {
            throw new \Exception('No image library available on your server');
        }
        $class = '\\'.__NAMESPACE__.'\Image'.$this->library;
        $this->image = new $class($source_filepath);
    }

    //Unknow methods will be redirected to image object
    public function __call(mixed $method, mixed $arguments)
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

    private function getResizeResult(string $destination_filepath, int $width, int $height, float $time = null)
    {
        return array(
            'source' => $this->source_filepath,
            'destination' => $destination_filepath,
            'width' => $width,
            'height' => $height,
            'size' => floor(filesize($destination_filepath) / 1024).' KB',
            'time' => $time ? number_format((microtime(true) - $time) * 1000, 2, '.', ' ').' ms' : null,
            'library' => $this->library
        );
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
            case self::LIBRARY_GD:
                if (self::isGD()) {
                    return self::LIBRARY_GD;
                }
            default:
                if ($library != 'auto') {
                    return self::getLibrary('auto', $extension);
                }
        }
        return false;
    }

    public function saveByDate(int $timestamp = null)
    {
        $this->config['save_by_date'] = true;
        if ($timestamp === null) {
            $timestamp = time();   
        }
        $date = new \DateTimeImmutable('@'.$timestamp);
        $path_date = $date->format('Y/m/d/');
        $this->param['path_date'] = $path_date;
    }

    public function startProcess()
    {
        return $this->image->startProcess();
    }

    public function setRootPath(string $root_path)
    {
        return $this->image->setRootPath($root_path);
    }
    public function getCreateFilePath()
    {
        return $this->image->getCreateFilePath();
    }

    public function getWidth()
    {
        return $this->image->getWidth();
    }

    public function getHeight()
    {
        return $this->image->getHeight();
    }

    public function setCompressionQuality(int $quality)
    {
        return $this->image->setCompressionQuality($quality);
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
            $check_dir = dirname($destination_filepath);
            if (!is_dir($check_dir) || !file_exists($check_dir)) {
                mkdir($check_dir, 0777, true);
            }
        }
        return $this->image->writeImage($destination_filepath);
    }

    public function destroyImage() 
    {
        if (method_exists($this->image, 'destroyImage')) {
            return $this->image->destroyImage();
        }
    }
}
