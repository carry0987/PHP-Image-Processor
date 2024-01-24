<?php
require dirname(__FILE__).'/../vendor/autoload.php';

use carry0987\Image\Image as Image;

$image = new Image(dirname(__FILE__).'/test.jpg', Image::LIBRARY_GD);
$image->startProcess()
    ->setCompressionQuality(60)
    ->saveByDate(1706097055)
    ->writeImage('test-modified.jpg');
$image->destroyImage();

if (file_exists($image->getCreatedPath())) {
    echo '<h1>Image modified successfully</h1>';
    echo '<img src="', $image->getCreatedPath(), '" />';
} else {
    echo '<h1>Image modified failed</h1>';
}
