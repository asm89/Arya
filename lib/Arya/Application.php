<?php

namespace Arya;

use Auryn\Injector,
    Auryn\Provider,
    Auryn\InjectionException,
    Arya\Routing\Router,
    Arya\Routing\CompositeRegexRouter;

class Application {

    private $injector;
    private $router;
    private $request;
    private $response;

    private $befores = array();
    private $afters = array();
    private $finalizers = array();
    private $errorModifiers = array();

    private $normalizeMethodCase = TRUE;
    private $allowEmptyPrimitiveResponse = FALSE;
    private $autoReason = TRUE;
    private $debug = TRUE;

    private $uriFilterWildcard = '*';
    private $isManuallyAddingHeadRoute = FALSE;

    public function __construct(Injector $injector = NULL, Router $router = NULL) {
        $this->injector = $injector ?: new Provider;
        $this->router = $router ?: new CompositeRegexRouter;
        $self = $this;
        register_shutdown_function(function() use ($self) { $self->shutdownHandler(); });
        set_exception_handler(function($e) use ($self) { $self->exceptionHandler($e); });
        ob_start();
    }

    /**
     * Add a route handler
     *
     * @param string $httpMethod
     * @param string $route
     * @param mixed $handler
     * @return AppRouteProxy
     */
    public function route($httpMethod, $uri, $handler) {
        if ($this->normalizeMethodCase) {
            $httpMethod = strtoupper($httpMethod);
        }
        if ($httpMethod === 'GET') {
            $this->isManuallyAddingHeadRoute = TRUE;
            $this->route('HEAD', $uri, $handler);
            $this->isManuallyAddingHeadRoute = FALSE;
        } elseif ($httpMethod === 'HEAD' && !$this->isManuallyAddingHeadRoute) {
            error_log(
                "HEAD requests are handled automatically by GET route handlers. " .
                "Please specify a GET route if you wish to respond to HEAD requests " .
                "for the {$uri} route."
            );
        }

        $this->router->addRoute($httpMethod, $uri, $handler);

        return new AppRouteProxy($this, $httpMethod, $uri);
    }

    /**
     * Register custom error response handlers
     *
     * If no $statusCode is specified the handler will be used for all error codes. Handlers
     * registered for specific codes take precedence over wildcard handlers.
     *
     * @param mixed $handler
     * @param mixed $statusCode
     * @throws TerminationException
     * @return Application Returns the current object instance
     */
    public function onError($handler, $statusCode = '*') {
        if ($statusCode === '*') {
            $this->errorModifiers[$statusCode] = $handler;
        } elseif (($statusCode = @intval($statusCode))
            && $statusCode >= 400
            && $statusCode <= 599
        ) {
            $this->errorModifiers[$statusCode] = $handler;
        } else {
            $this->outputManualExceptionResponse(new \DomainException(
                sprintf('Invalid onError status code: %s', $statusCode)
            ));
        }
    }

    /**
     * Attach a "before" middleware
     *
     * @param mixed $middleware
     * @param array $options
     * @return Application Returns the current object instance
     */
    public function before($middleware, array $options = array()) {
        $this->befores[] = $this->generateMiddlewareComponents($middleware, $options);

        return $this;
    }

    private function generateMiddlewareComponents($middleware, array $options) {
        $methodFilter = empty($options['method']) ? NULL : $options['method'];
        $uriFilter = empty($options['uri']) ? NULL : $options['uri'];
        $priority = isset($options['priority']) ? @intval($options['priority']) : 50;

        return array($middleware, $methodFilter, $uriFilter, $priority);
    }

    /**
     * Attach an "after" middleware
     *
     * @param mixed $middleware
     * @param array $options
     * @return Application Returns the current object instance
     */
    public function after($middleware, array $options = array()) {
        $this->afters[] = $this->generateMiddlewareComponents($middleware, $options);

        return $this;
    }

    /**
     * Attach a "finalize" middleware
     *
     * @param mixed $middleware
     * @param array $options
     * @return Application Returns the current object instance
     */
    public function finalize($middleware, array $options = array()) {
        $this->finalizers[] = $this->generateMiddlewareComponents($middleware, $options);

        return $this;
    }

    /**
     * Respond to client requests
     *
     * @param array $request The request environment
     * @return void
     */
    public function run(Request $request = NULL) {
        $request = $request ?: $this->generateRequest();
        $this->request = $request;
        $this->injector->share($request);
        $this->sortMiddlewareByPriority();

        if (!$response = $this->doBefores($request)) {
            $response = $this->routeRequest($request);
        }

        $this->response = $this->doAfters($request, $response);

        $bufferedOutput = ob_get_contents();
        if (isset($bufferedOutput[0])) {
            $this->response = $this->generateOutputErrorResponse($bufferedOutput);
        }
        ob_end_clean();

        $this->sendResponse($this->response);
    }

    private function generateRequest() {
        $input = !empty($_SERVER['CONTENT-LENGTH']) ? fopen('php://input', 'r') : NULL;
        $request = new Request($_SERVER, $_GET, $_POST, $_FILES, $_COOKIE, $input);

        return $request;
    }

    private function sortMiddlewareByPriority() {
        $sortFunction = [$this, 'middlewareSort'];

        if ($this->befores) {
            usort($this->befores, $sortFunction);
        }
        if ($this->afters) {
            usort($this->afters, $sortFunction);
        }
        if ($this->finalizers) {
            usort($this->finalizers, $sortFunction);
        }
    }

    private function middlewareSort(array $a, array $b) {
        $a = end($a);
        $b = end($b);

        if ($a == $b) {
            $result = 0;
        } else {
            $result = ($a < $b) ? -1 : 1;
        }

        return $result;
    }

    private function doBefores($request) {
        $result = NULL;

        foreach ($this->befores as $middlewareArray) {
            $result = $this->applyBefore($middlewareArray, $request);
            if (!isset($result)) {
                continue;
            } elseif ($result instanceof Response) {
                break;
            } else {
                $result = $this->assignPrimitiveBeforeMiddlewareResponse($result);
                break;
            }
        }

        return $result;
    }

    private function assignPrimitiveBeforeMiddlewareResponse($result) {
        try {
            $response = new Response;
            $response->setBody($result);
        } catch (\InvalidArgumentException $e) {
            $response = $this->generateExceptionResponse(new \LogicException(
                sprintf('"before" middleware returned invalid type: %s', gettype($result))
            ));
        }

        return $response;
    }

    private function applyBefore(array $middlewareArray, Request $request) {
        list($middleware, $methodFilter, $uriFilter) = $middlewareArray;

        if ($methodFilter && $request['REQUEST_METHOD'] !== $methodFilter) {
            $result = NULL;
        } elseif ($uriFilter && !$this->matchesUriFilter($uriFilter, $request['REQUEST_URI'])) {
            $result = NULL;
        } else {
            $result = $this->tryBefore($middleware, $request);
        }

        return $result;
    }

    private function tryBefore($middleware, Request $request) {
        try {
            $result = $this->injector->execute($middleware);
        } catch (InjectionException $e) {
            $result = $this->generateExceptionResponse(new \RuntimeException(
                $msg = '"Before" middleware injection failure',
                $code = 0,
                $prev = $e
            ));
        } catch (\Exception $e) {
            $result = $this->generateExceptionResponse(new \RuntimeException(
                $msg = '"Before" middleware execution threw an uncaught exception',
                $code = 0,
                $prev = $e
            ));
        }

        return $result;
    }

    private function matchesUriFilter($uriFilter, $uriPath) {
        if ($uriFilter === $uriPath) {
            $isMatch = TRUE;
        } elseif ($uriFilter[strlen($uriFilter) - 1] === $this->uriFilterWildcard
            && strpos($uriPath, substr($uriFilter, 0, -1)) === 0
        ) {
            $isMatch = TRUE;
        } else {
            $isMatch = FALSE;
        }

        return $isMatch;
    }

    private function generateExceptionResponse(\Exception $e) {
        $response = new Response;
        $response->setStatus(Status::INTERNAL_SERVER_ERROR);
        $response->setReason(Reason::HTTP_500);
        $response->setBody($this->generateExceptionBody($e));

        return $response;
    }

    private function generateExceptionBody(\Exception $e) {
        $msg = $this->debug
            ? "<pre style=\"color:red\">{$e}</pre>"
            : '<p>Something went terribly wrong!</p>';

        return "<html><body><h1>500 Internal Server Error</h1><hr/>{$msg}</body></html>";
    }

    private function generateOutputErrorResponse($buffer) {
        $response = new Response;
        $response->setStatus(Status::INTERNAL_SERVER_ERROR);
        $response->setReason(Reason::HTTP_500);

        $msg = $this->debug
            ? "<pre style=\"color:red\">{$buffer}</pre>"
            : '<p>Something went terribly wrong!</p>';

        $body = "<html><body><h1>500 Internal Server Error</h1><hr/>{$msg}</body></html>";

        $response->setBody($body);

        return $response;
    }

    private function routeRequest(Request $request) {
        try {
            list($routeHandler, $routeArgs) = $this->router->route(
                $request['REQUEST_METHOD'],
                $request['REQUEST_URI_PATH']
            );

            $request['ROUTE_ARGS'] = $routeArgs;
            $argLiterals = array();

            if ($routeArgs) {
                foreach ($routeArgs as $key => $value) {
                    $argLiterals[":{$key}"] = $value;
                }
            }

            $result = $this->injector->execute($routeHandler, $argLiterals);

            if ($result instanceof Response) {
                $response = $result;
            } elseif (is_array($result)) {
                $response = new Response;
                $response->populateFromAsgiMap($result);
            } else {
                $response = new Response;
                $response->setBody($result);
                $this->validateEmptyPrimitiveResponse($response);
            }
        } catch (NotFoundException $e) {
            $response = $this->generateNotFoundResponse();
        } catch (MethodNotAllowedException $e) {
            $allowedMethods = $e->getAllowedMethods();
            $response = $this->generateMethodNotAllowedResponse($allowedMethods);
        } catch (InjectionException $e) {
            $response = $this->generateExceptionResponse(new \RuntimeException(
                $msg = 'Route handler injection failure',
                $code = 0,
                $prev = $e
            ));
        } catch (\Exception $e) {
            $response = $this->generateExceptionResponse($e);
        }

        return $response;
    }

    private function validateEmptyPrimitiveResponse(Response $response) {
        if (!($this->allowEmptyPrimitiveResponse || ($body = $response->getBody()) || $body === '0')) {
            throw new \LogicException(
                'Empty primitive response'
            );
        }
    }

    private function generateNotFoundResponse() {
        $response = new Response;
        $response->setStatus(Status::NOT_FOUND);
        $response->setReason(Reason::HTTP_404);
        $response->setBody('<html><body><h1>404 Not Found</h1></body></html>');

        return $response;
    }

    private function generateMethodNotAllowedResponse(array $allowedMethods) {
        $response = new Response;
        $response->setStatus(Status::METHOD_NOT_ALLOWED);
        $response->setReason(Reason::HTTP_405);
        $response->setHeader('Allow', implode(',', $allowedMethods));
        $response->setBody('<html><body><h1>405 Method Not Allowed</h1></body></html>');

        return $response;
    }

    private function doAfters(Request $request, Response $response) {
        foreach ($this->afters as $middlewareArray) {
            if ($errorResponse = $this->applyAfter($middlewareArray, $request, $response)) {
                $response = $errorResponse;
                break;
            }
        }

        return $response;
    }

    private function applyAfter(array $middlewareArray, Request $request, Response $response) {
        list($middleware, $methodFilter, $uriFilter) = $middlewareArray;

        if ($methodFilter && $request['REQUEST_METHOD'] !== $methodFilter) {
            $result = NULL;
        } elseif ($uriFilter && !$this->matchesUriFilter($uriFilter, $request['REQUEST_URI'])) {
            $result = NULL;
        } else {
            $result = $this->tryAfter($middleware, $request, $response);
        }

        return $result;
    }

    private function tryAfter($middleware, Request $request, Response $response) {
        try {
            $this->injector->execute($middleware, [$request, $response]);
        } catch (\Exception $e) {
            return $this->generateExceptionResponse($e);
        }
    }

    private function sendResponse(Response $response) {
        $statusCode = $response->getStatus();
        if ($statusCode >= 400) {
            $response = $this->modifyErrorResponse($statusCode, $response);
        }

        if ($this->autoReason && !$response->getReason()) {
            $reasonConstant = "Thing\Reason::HTTP_{$statusCode}";
            $reason = defined($reasonConstant) ? constant($reasonConstant) : '';
            $response->setReason($reason);
        }

        $protocol = $this->request->getOriginalVar('SERVER_PROTOCOL');
        $statusLine = $this->generateResponseStatusLine($response, $protocol);
        header($statusLine);

        foreach ($response->getAllHeaderLines() as $headerLine) {
            header($headerLine);
        }

        $body = $response->getBody();

        if (is_string($body)) {
            echo $body;
        } elseif (is_callable($body)) {
            $this->outputCallableBody($body);
        }
    }

    private function generateResponseStatusLine(Response $response, $protocol) {
        $status = $response->getStatus();
        $reason = $response->getReason();
        $statusLine = "HTTP/{$protocol} {$status}";

        if (isset($reason[0])) {
            $statusLine .= " {$reason}";
        }

        return $statusLine;
    }

    private function modifyErrorResponse($statusCode, Response $response) {
        if (isset($this->errorModifiers[$statusCode])) {
            $modifier = $this->errorModifiers[$statusCode];
            $response = $this->tryErrorModification($modifier, $this->request, $response);
        } elseif (isset($this->errorModifiers['*'])) {
            $modifier = $this->errorModifiers['*'];
            $response = $this->tryErrorModification($modifier, $this->request, $response);
        }

        return $response;
    }

    private function tryErrorModification($modifier, Request $request, Response $response) {
        try {
            $executable = $this->injector->getExecutable($modifier);
            $result = $executable($request, $response);
            if ($result instanceof Response) {
                $response = $result;
            } else {
                $response->setBody($result);
            }
        } catch (InjectionException $e) {
            $response = $this->generateExceptionResponse(new \RuntimeException(
                $msg = 'Error modifier injection failure',
                $code = 0,
                $prev = $e
            ));
        } catch (\Exception $e) {
            $response = $this->generateExceptionResponse(new \RuntimeException(
                $msg = 'Error modifier execution threw an uncaught exception',
                $code = 0,
                $prev = $e
            ));
        }

        return $response;
    }

    private function outputCallableBody(callable $body) {
        try {
            $body();
        } catch (\Exception $e) {
            $this->outputManualExceptionResponse($e);
        }
    }

    private function outputManualExceptionResponse(\Exception $e) {
        if (!headers_sent()) {
            header_remove();
            $protocol = $this->request->getOriginalVar('SERVER_PROTOCOL');
            header("HTTP/{$protocol} 500 Internal Server Error");
            echo $this->generateExceptionBody($e);
        }

        throw new TerminationException;
    }

    private function shutdownHandler() {
        $fatals = array(E_ERROR, E_PARSE, E_USER_ERROR, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING);
        $lastError = error_get_last();

        if ($lastError && in_array($lastError['type'], $this->fatals)) {
            extract($lastError);
            $this->outputManualExceptionResponse(new \RuntimeException(
                sprintf("Fatal error: %s in %s on line %d", $message, $file, $line)
            ));
        }

        foreach ($this->finalizers as $middlewareArray) {
            $this->applyFinalizers($middlewareArray[0]);
        }
    }

    private function applyFinalizers($middleware) {
        try {
            $this->injector->execute($middleware, []);
        } catch (\Exception $e) {
            error_log($e->__toString());
        }
    }

    private function exceptionHandler(\Exception $e) {
        if ($e instanceof TerminationException) {
            exit;
        } else {
            throw $e;
        }
    }

}
