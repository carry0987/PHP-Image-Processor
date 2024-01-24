<?php
namespace carry0987\Image\Interface;

use carry0987\Image\Image;

//Define all needed method for image class
interface ImageInterface
{
    public function setAllowType(array|string $allow_type): self;
    public function checkFileType(string $filepath): self;
    public function setRootPath(string $root_path): self;
    public function setCompressionQuality(int $quality): self;
    public function startProcess(): self;
    public function getCreatedPath();
    public function getWidth();
    public function getHeight();
    public function cropImage(int $width, int $height, int $x, int $y);
    public function cropSquare(int $width);
    public function stripImage();
    public function rotateImage(float $rotation);
    public function resizeImage(int $width, int $height);
    public function sharpenImage(float $amount);
    public function compositeImage(Image $overlay, int $x, int $y, int $opacity);
    public function correctImageOrientation(): self;
    public function writeImage(string $destination_filepath);
    public function destroyImage(): bool;
}
