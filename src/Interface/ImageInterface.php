<?php
namespace carry0987\Image\Interface;

use carry0987\Image\Image;

//Define all needed method for image class
interface ImageInterface
{
    public function setAllowType(array $allow_type): self;
    public function checkFileType(string $filepath): self;
    public function setRootPath(string $root_path): self;
    public function setCompressionQuality(int $quality): self;
    public function startProcess(): self;
    public function getRootPath(): ?string;
    public function getCreatedPath(bool $full_path = false): ?string;
    public function getWidth(): int;
    public function getHeight(): int;
    public function cropImage(int $width, int $height, int $x, int $y): bool;
    public function cropSquare(int $width): bool;
    public function stripImage(): bool;
    public function rotateImage(float $rotation): void;
    public function resizeImage(int $width, int $height): bool;
    public function sharpenImage(float $amount): bool;
    public function compositeImage(Image $overlay, int $x, int $y, int $opacity): bool;
    public function correctImageOrientation(): self;
    public function writeImage(string $destination_filepath): bool;
    public function destroyImage(): bool;
}
