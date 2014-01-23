<?php

namespace Arya;

class Request implements \ArrayAccess, \Iterator {

    private $headers = array();
    private $query = array();
    private $form = array();
    private $files = array();
    private $cookies = array();
    private $vars = array();
    private $bodyStream;
    private $body;

    private $isEncrypted;
    private $inMemoryBodyStreamSize = 2097152;
    private $isInputStreamCopied = FALSE;

    public function __construct(
        array $_server,
        array $_get,
        array $_post,
        array $_files,
        array $_cookie,
        $_input
    ) {
        $this->processServer($_server);

        $this->query = $_get;
        $this->form = $_post;
        $this->files = $_files;
        $this->cookies = $_cookie;

        if ($_input) {
            $this->processInput($_input);
        }
    }

    private function processServer(array $_server) {
        foreach ($_server as $key => $value) {
            $this->vars[$key] = $value;
            if (strpos($key, 'HTTP_') === 0) {
                $key = str_replace('_', '-', substr($key, 5));
                $this->headers[$key] = $value;
            }
        }

        $isEncrypted = !(empty($_server['HTTPS']) || strcasecmp($_server['HTTPS'], 'off') === 0);
        $this->isEncrypted = $isEncrypted;
        $this->vars['HTTPS'] = $isEncrypted;
        $this->vars['SERVER_PROTOCOL'] = substr($_server['SERVER_PROTOCOL'], -3);

        if (isset($_server['CONTENT_LENGTH'])) {
            $this->headers['CONTENT-LENGTH'] = $_server['CONTENT_LENGTH'];
        }

        if (isset($_server['CONTENT_TYPE'])) {
            $this->headers['CONTENT-TYPE'] = $_server['CONTENT_TYPE'];
        }

        $uriPath = parse_url($_server['REQUEST_URI'], PHP_URL_PATH);
        $this->vars['REQUEST_URI_PATH'] = $uriPath;
        $this->originalVars = $this->vars;
    }

    private function processInput($_input) {
        $this->bodyStream = $_input;

        if ($_input
            && $this->vars['REQUEST_METHOD'] !== 'POST'
            && stripos($this->vars['CONTENT_TYPE'], 'multpart/form-data') === 0
        ) {
            $this->parseMultipartFormData($_input);
        }
    }

    /**
     * @TODO Parse non-POST-method multipart bodies into form/files arrays
     */
    private function parseMultipartFormData($_input) {}
    
    
    
    
    


    public function hasHeader($field) {
        return isset($this->headers[strtoupper($field)]);
    }

    public function getHeader($field) {
        $ucField = strtoupper($field);
        if (isset($this->headers[$ucField])) {
            return $this->headers[$ucField];
        } else {
            throw new \DomainException(
                sprintf("Unknown header field: %s", $field)
            );
        }
    }

    public function getAllHeaders() {
        return $this->headers;
    }

    public function hasQueryParameter($field) {
        return isset($this->query[$field]);
    }

    public function getQueryParameter($field) {
        if (isset($this->query[$field])) {
            return $this->query[$field];
        } else {
            throw new \DomainException(
                sprintf("Unknown query parameter: %s", $field)
            );
        }
    }

    public function getAllQueryParameters() {
        return $this->query;
    }

    public function hasFormField($field) {
        return isset($this->form[$field]);
    }

    public function getFormField($field) {
        if (isset($this->form[$field])) {
            return $this->form[$field];
        } else {
            throw new \DomainException(
                sprintf("Unknown form field: %s", $field)
            );
        }
    }

    public function getAllFormFields() {
        return $this->form;
    }

    public function hasFormFile($field) {
        return isset($this->files[$field]);
    }

    public function getFormFile($field) {
        if (isset($this->files[$field])) {
            return $this->files[$field];
        } else {
            throw new \DomainException(
                sprintf("Unknown form file: %s", $field)
            );
        }
    }

    public function getAllFormFiles() {
        return $this->files;
    }

    public function hasCookie($field) {
        return isset($this->cookies[$field]);
    }

    public function getCookie($field) {
        if (isset($this->cookies[$field])) {
            return $this->cookies[$field];
        } else {
            throw new \DomainException(
                sprintf("Unknown cookie field: %s", $field)
            );
        }
    }

    public function getAllCookies() {
        return $this->cookies;
    }

    public function hasBody() {
        return isset($this->bodyStream) || isset($this->body);
    }

    public function getBody() {
        if (isset($this->body)) {
            $body = $this->body;
        } elseif ($this->bodyStream) {
            $body = $this->bufferBodyStream();
        } else {
            $body = NULL;
        }

        return $body;
    }

    private function bufferBodyStream() {
        $bodyStream = $this->copyInputStream();
        $bufferedBody = stream_get_contents($bodyStream);
        rewind($bodyStream);
        $this->bodyStream = $bodyStream;
        $this->body = $bufferedBody;

        return $bufferedBody;
    }

    private function copyInputStream() {
        $tmpPath = sprintf("php://temp/maxmemory:%d", $this->inMemoryBodyStreamSize);
        if (!$tmpStream = fopen($tmpPath, 'w+')) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(
                'Failed opening temporary entity body stream'
            );
            // @codeCoverageIgnoreEnd
        }

        stream_copy_to_stream($this->bodyStream, $tmpStream);
        rewind($tmpStream);
        $this->isInputStreamCopied = TRUE;

        return $tmpStream;
    }

    public function getBodyStream() {
        return $this->isInputStreamCopied ? $this->bodyStream : $this->getBody();
    }

    public function set($offset, $value) {
        $this->vars[$offset] = $value;
    }

    public function has($field) {
        return isset($this->vars[$field]);
    }

    public function get($field) {
        if (isset($this->vars[$field])) {
            return $this->vars[$field];
        } else {
            throw new \DomainException(
                sprintf("Unknown request variable: %s", $field)
            );
        }
    }

    public function all() {
        return $this->vars;
    }

    public function isEncrypted() {
        return $this->isEncrypted;
    }

    public function offsetSet($offset, $value) {
        $this->vars[$offset] = $value;
    }

    public function offsetExists($offset) {
        return isset($this->vars[$offset]);
    }

    public function offsetUnset($offset) {
        unset($this->vars[$offset]);
    }

    public function offsetGet($offset) {
        if (isset($this->vars[$offset]) || array_key_exists($offset, $this->vars)) {
            return $this->vars[$offset];
        } else {
            throw new \DomainException(
                sprintf("Unknown request variable: %s", $offset)
            );
        }
    }

    public function rewind() {
        reset($this->vars);
    }

    public function current() {
        return current($this->vars);
    }

    public function key() {
        return key($this->vars);
    }

    public function next() {
        return next($this->vars);
    }

    public function valid() {
        $key = key($this->vars);

        return isset($this->vars[$key]) || array_key_exists($key, $this->vars);
    }

}
