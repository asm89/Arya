<?php

namespace Arya\Routing;

interface Router {

    /**
     * @param string $httpMethod
     * @param string $route
     * @param mixed $handler
     * @return void
     */
    public function addRoute($httpMethod, $route, $handler);

    /**
     * @param string $httpMethod
     * @param string $uri
     * @return mixed
     */
    public function route($httpMethod, $uri);

}
