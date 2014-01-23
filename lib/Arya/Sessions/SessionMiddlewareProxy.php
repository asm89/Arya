<?php

namespace Arya\Sessions;

use Arya\Application,
    Arya\Request;

class SessionMiddlewareProxy extends Session {

    function __construct(Application $app, Request $request, SessionHandler $handler = NULL, $priority = 20) {
        parent::__construct($request, $handler);

        $middlewareOptions = array(
            'priority' => $priority,
            'uri' => $request['REQUEST_URI'],
            'method' => $request['REQUEST_METHOD']
        );
        $self = $this;
        $app->after(function($request, $response) use ($self) {
            if ($self->shouldSetCookie()) {
                $self->close();
                list($name, $value, $options) = $self->getCookieElements();
                $response->setRawCookie($name, $value, $options);
                $cacheControlHeader = $self->generateCacheControlHeader();
                $response->setHeader('Cache-Control', $cacheControlHeader);
            }
        }, $middlewareOptions);
    }

    private function generateCacheControlHeader() {
        $header = $this->getOption('cache_limiter');

        if ($header !== Session::CACHE_NOCACHE) {
            $cacheExpire = $this->getOption('cache_expire');
            $header .= ", max-age={$cacheExpire}, pre-check={$cacheExpire}";
        }

        return $header;
    }

}
