<?php
namespace carry0987\Image\Exception;

class UnsupportedLibraryException extends \Exception
{
    protected $message = 'No image library available on your server.';
}
