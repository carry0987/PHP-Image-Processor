<?php
require dirname(__FILE__).'/../vendor/autoload.php';

use carry0987\Image\Image as Image;

$image = new Image(dirname(__FILE__).'/test.jpg', Image::LIBRARY_GD);
$image->startProcess();
$image->setCompressionQuality(60);
$image->writeImage('test-modified.jpg');
$image->destroyImage();

if (file_exists('test-modified.jpg')) {
    echo '<h1>Image modified successfully</h1>';
    echo '<img src="test-modified.jpg" />';
} else {
    echo '<h1>Image modified failed</h1>';
}
