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
    //Inner variable
    private $config = array(
        'save_by_date' => false
    );
    private $param = array(
        'path_date' => null,
        'extension' => null
    );

    const LIBRARY_GD = 'GD';
    const LIBRARY_IMAGICK = 'Imagick';
    const DIR_SEP = DIRECTORY_SEPARATOR;

    public function __construct(string $source_filepath, ?string $library = null)
    {
        if (!file_exists($source_filepath)) {
            throw new IOException();
        }
        $this->source_filepath = $source_filepath;
        if (is_object($this->image)) return;
        if (!($this->library = self::getLibrary($library))) {
            throw new UnsupportedLibraryException();
        }
        $this->initLibrary($source_filepath);
    }

    /**
     *  Unknow methods will be redirected to image object
     * @param string $method
     * @param array $arguments
     * 
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        return call_user_func_array(array($this->image, $method), $arguments);
    }

    public function setAllowType(array|string $allow_type): self
    {
        if (!is_array($allow_type)) {
            $filtered = array_filter(explode(',', $allow_type), function($str) {
                return !empty($str);
            });
            $allow_type = array_values($filtered);
        }
        $allow_type = array_map(function($type) {
            return 'image/'.strtolower(trim($type));
        }, $allow_type);
        $this->image->setAllowType($allow_type);

        return $this;
    }

    public static function getFileExtension(string $filename, bool $to_lower = true): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        return ($to_lower === true) ? strtolower($ext) : $ext;
    }

    //Returns a normalized convolution kernel for sharpening
    public static function getSharpenMatrix(float $amount): array
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

    public static function isImagick(): bool
    {
        return (extension_loaded('imagick') && class_exists('Imagick'));
    }

    public static function isGD(): bool
    {
        return function_exists('gd_info');
    }

    public static function getLibrary(string $library): ?string
    {
        if (is_null($library)) {
            $library = self::LIBRARY_GD;
        }
        switch (strtolower($library)) {
            case 'auto':
            case strtolower(self::LIBRARY_IMAGICK):
                if (self::isImagick()) {
                    return self::LIBRARY_IMAGICK;
                }
                break;
            case strtolower(self::LIBRARY_GD):
                if (self::isGD()) {
                    return self::LIBRARY_GD;
                }
                break;
            default:
                if ($library != 'auto') {
                    return self::getLibrary('auto');
                }
                break;
        }

        return null;
    }

    public function saveByDate(?int $timestamp = null): self
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

    public function setFormat(string $extension): self
    {
        $this->param['extension'] = strtolower($extension);

        return $this;
    }

    public function getRootPath(): ?string
    {
        return $this->image->getRootPath();
    }

    public function getCreatedPath(bool $full_path = false): ?string
    {
        return $this->image->getCreatedPath($full_path);
    }

    public function getWidth(): int
    {
        return $this->image->getWidth();
    }

    public function getHeight(): int
    {
        return $this->image->getHeight();
    }

    public function cropImage(int $width, int $height, int $x, int $y): bool
    {
        return $this->image->cropImage($width, $height, $x, $y);
    }

    public function cropSquare(int $width): bool
    {
        return $this->image->cropSquare($width);
    }

    public function stripImage(): bool
    {
        return $this->image->stripImage();
    }

    public function rotateImage(float $rotation): void
    {
        $this->image->rotateImage($rotation);
    }

    public function resizeImage(int $width, int $height): bool
    {
        return $this->image->resizeImage($width, $height);
    }

    public function sharpenImage(float $amount): bool
    {
        return $this->image->sharpenImage($amount);
    }

    public function compositeImage(Image $overlay, int $x, int $y, int $opacity): bool
    {
        return $this->image->compositeImage($overlay, $x, $y, $opacity);
    }

    public function writeImage(string $destination_filepath, bool $in_root_path = false): bool
    {
        if (!empty($destination_filepath)) {
            if ($this->config['save_by_date'] === true) {
                $path_date = $this->param['path_date'] ?? '';
                $dir_path = dirname($destination_filepath);
                $destination_filepath = self::trimPath($dir_path.'/'.$path_date.'/'.basename($destination_filepath));
            }
            $check_dir = dirname($destination_filepath);
            if (!is_dir($check_dir) || !file_exists($check_dir)) {
                mkdir($check_dir, 0755, true);
            }
        }
        if ($in_root_path === true) {
            $destination_filepath = ((string) $this->getRootPath()).'/'.$destination_filepath;
            $destination_filepath = self::trimPath($destination_filepath);
        }

        // Set format
        if ($this->param['extension'] !== null) {
            $destination_filepath = self::replaceExtension($destination_filepath, $this->param['extension']);
        }

        // Write image
        $result = $this->image->writeImage($destination_filepath);

        return $result;
    }

    public function destroyImage(): bool
    {
        return $this->image->destroyImage();
    }

    private function initLibrary(string $source_filepath): void
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

    private static function trimPath(string $path): string
    {
        return str_replace(array('/', '\\', '//', '\\\\'), self::DIR_SEP, $path);
    }

    private static function replaceExtension(string $path, ?string $extension): string
    {
        if (empty($extension)) return $path;

        $info = pathinfo($path);
        $dirname = $info['dirname'];
        $filename = $info['filename'];

        return self::trimPath($dirname.self::DIR_SEP.$filename.'.'.$extension);
    }
}
