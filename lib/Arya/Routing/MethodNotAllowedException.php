<?php

namespace Arya\Routing;

class MethodNotAllowedException extends \RuntimeException {
    
    private $allowedMethods;
    
    public function __construct(array $allowedMethods) {
        $this->allowedMethods = $allowedMethods;
    }
    
    public function getAllowedMethods() {
        return $this->allowedMethods;
    }
    
}
