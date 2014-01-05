<?php

namespace Arya\Routing;

class MethodNotAllowedException extends \RuntimeException {
    
    private $allowedMethods;
    
    function __construct(array $allowedMethods) {
        $this->allowedMethods = $allowedMethods;
    }
    
    function getAllowedMethods() {
        return $this->allowedMethods;
    }
    
}
