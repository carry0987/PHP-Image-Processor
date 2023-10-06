<?php
namespace carry0987\Image\Interface;

//Define all needed method for image class
interface ImageInterface
{
    public function setAllowType($allow_type);
    public function checkFileType($filepath);
    public function startProcess();
    public function setRootPath($root_path);
    public function getCreateFilePath();
    public function getWidth();
    public function getHeight();
    public function setCompressionQuality($quality);
    public function cropImage($width, $height, $x, $y);
    public function cropSquare($size);
    public function stripImage();
    public function rotateImage($rotation);
    public function resizeImage(int $width, int $height);
    public function sharpenImage($amount);
    public function compositeImage($overlay, $x, $y, $opacity);
    public function writeImage($destination_filepath);
    public function destroyImage();
}
