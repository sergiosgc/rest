<?php
namespace sergiosgc\rest;

class Exception extends \Exception { }

class NotFoundException extends Exception {
    public function __construct($message = "", $code = 0, $previous = NULL) {
        if ($message == '') $message = 'Not Found';
        header(sprintf('HTTP/1.0 404 %s', $message));
        parent::__construct($message, $code, $previous);
    }
}
