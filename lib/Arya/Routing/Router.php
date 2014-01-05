<?php

namespace Arya\Routing;

interface Router {

    /**
     * @param string $httpMethod
     * @param string $route
     * @param mixed $handler
     * @return void
     */
    function addRoute($httpMethod, $route, $handler);

    /**
     * @param string $httpMethod
     * @param string $uri
     * @return mixed
     */
    function route($httpMethod, $uri);

}
