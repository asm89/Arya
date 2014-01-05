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

        if ($_get) {
            $this->processGet($_get);
        }
        if ($_post) {
            $this->processPost($_post);
        }
        if ($_files) {
            $this->processFiles($_files);
        }
        if ($_cookie) {
            $this->processCookies($_cookie);
        }
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

        $this->isEncrypted = !(empty($_server['HTTPS']) || strcasecmp($_server['HTTPS'], 'off') === 0);

        if (isset($_server['CONTENT_LENGTH'])) {
            $contentLength = $_server['CONTENT_LENGTH'];
            $this->headers['CONTENT-LENGTH'] = $contentLength;
        }

        if (isset($_server['CONTENT_TYPE'])) {
            $contentType = $_server['CONTENT_TYPE'];
            $this->headers['CONTENT-TYPE'] = $contentType;
        }

        $uriPath = parse_url($_server['REQUEST_URI'], PHP_URL_PATH);
        $this->vars['REQUEST_URI_PATH'] = $uriPath;
        $this->originalVars = $this->vars;
    }

    private function processGet(array $_get) {
        foreach ($_get as $key => $value) {
            $this->query[$key] = $value;
        }
    }

    private function processPost(array $_post) {
        foreach ($_post as $key => $value) {
            $this->form[$key] = $value;
        }
    }

    private function processFiles(array $_files) {
        foreach ($_files as $key => $value) {
            $this->files[$key] = $value;
        }
    }

    private function processCookies(array $_cookies) {
        foreach ($_cookies as $key => $value) {
            $this->cookies[$key] = $value;
        }
    }

    private function processInput($_input) {
        if ($this->bodyStream = $_input
            && $this->vars['REQUEST_METHOD'] !== 'POST'
            && stripos($this->vars['CONTENT_TYPE'], 'multpart/form-data') === 0
        ) {
            $this->parseMultipartFormData($_input);
        }
    }

    private function parseMultipartFormData($_input) {
        // @TODO Parse non-POST-method multipart bodies into form/files arrays
    }

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

    public function getFormFileName($field) {
        return $this->getFormFileProperty($field, 'name');
    }

    public function getFormFileType($field) {
        return $this->getFormFileProperty($field, 'type');
    }

    public function getFormFileSize($field) {
        return $this->getFormFileProperty($field, 'size');
    }

    public function getFormFileTmpName($field) {
        return $this->getFormFileProperty($field, 'tmp_name');
    }

    public function getFormFileError($field) {
        return $this->getFormFileProperty($field, 'error');
    }

    private function getFormFileProperty($field, $property) {
        if (isset($this->files[$field])) {
            return $this->files[$field][$property];
        } else {
            throw new \DomainException(
                sprintf("Unknown form file: %s", $field)
            );
        }
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
        $bufferedBody = stream_get_contents($tmpStream);
        rewind($tmpStream);
        $this->bodyStream = $tmpStream;
        $this->body = $bufferedBody;

        return $bufferedBody;
    }

    private function copyInputStream() {
        $tmpPath = sprintf("php://temp/maxmemory:%d", $this->inMemoryBodyStreamSize);
        if (!$tmpStream = @fopen($tmpPath, 'w+')) {
            throw new \RuntimeException(
                'Failed opening temporary entity body stream'
            );
        }

        stream_copy_to_stream($this->bodyStream, $tmpStream);
        rewind($tmpStream);
        $this->isInputStreamCopied = TRUE;

        return $tmpStream;
    }

    public function getBodyStream() {
        return $this->isInputStreamCopied ? $this->bodyStream : $this->copyInputStream();
    }

    public function saveBodyTo($destinationPath, $flags = LOCK_EX) {
        if (!isset($this->bodyStream)) {
            throw new \LogicException(
                // @TODO
            );
        } elseif (!is_writable(dirname($destinationPath))) {
            throw new \RuntimeException(
                // @TODO
            );
        }

        $body = $this->isInputStreamCopied ? $this->body : $this->bodyStream;

        if (FALSE === @file_put_contents($destinationPath, $body, $flags)) {
            throw new \RuntimeException(
                // @TODO
            );
        }
    }

    public function hasVar($field) {
        return isset($this->vars[$field]);
    }

    public function getVar($field) {
        if (isset($this->vars[$field])) {
            return $this->vars[$field];
        } else {
            throw new \DomainException(
                sprintf("Unknown request variable: %s", $field)
            );
        }
    }

    public function getAllVars() {
        return $this->vars;
    }

    public function hasOriginalVar($field) {
        return isset($this->originalVars[$field]);
    }

    public function getOriginalVar($field) {
        if (isset($this->originalVars[$field])) {
            return $this->originalVars[$field];
        } else {
            throw new \DomainException(
                sprintf("Unknown request variable: %s", $field)
            );
        }
    }

    public function getAllOriginalVars() {
        return $this->originalVars;
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
