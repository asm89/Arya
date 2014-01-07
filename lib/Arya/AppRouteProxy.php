<?php

namespace Arya;

class AppRouteProxy {

    private $app;
    private $method;
    private $uri;

    public function __construct(Application $app, $method, $uri) {
        $this->app = $app;
        $this->method = $method;
        $this->uri = $uri;
    }

    /**
     * Assign a "before" middleware ONLY for this object's HTTP method and URI
     *
     * @param mixed $middleware
     * @param int $priority
     * @return AppRouteProxy Returns current object instance
     */
    public function beforeRoute($middleware, $priority = 50) {
        $this->app->before($middleware, $options = array(
            'method' => $this->method,
            'uri' => $this->uri,
            'priority' => $priority
        ));

        return $this;
    }

    /**
     * Assign an "after" middleware ONLY for this object's HTTP method and URI
     *
     * @param mixed $middleware
     * @param int $priority
     * @return AppRouteProxy Returns current object instance
     */
    public function afterRoute($middleware, $priority = 50) {
        $this->app->after($middleware, $options = array(
            'method' => $this->method,
            'uri' => $this->uri,
            'priority' => $priority
        ));

        return $this;
    }

    /**
     * Assign a "finalize" middleware ONLY for this object's HTTP method and URI
     *
     * @param mixed $middleware
     * @param int $priority
     * @return AppRouteProxy Returns current object instance
     */
    public function finalizeRoute($middleware, $priority = 50) {
        $this->app->finalize($middleware, $options = array(
            'method' => $this->method,
            'uri' => $this->uri,
            'priority' => $priority
        ));

        return $this;
    }

    /**
     * Route all other calls to the Application object
     *
     * @param string $method
     * @param array $args
     * @throws \BadMethodCallException
     * @return mixed
     */
    public function __call($method, array $args) {
        $callable = [$this->app, $method];

        if (!is_callable($callable)) {
            throw new \BadMethodCallException(
                sprintf('Method does not exist: %s::%s', get_class($this), $method)
            );
        } elseif ($args) {
            return call_user_func_array($callable, $args);
        } else {
            return $callable();
        }
    }

}
