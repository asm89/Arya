<?php

namespace Arya;

class Response extends \Asgi\Response {

    /**
     * Assign a response entity body
     *
     * @param mixed[string|callable|Body] $body
     * @throws \InvalidArgumentException
     * @return Response Returns the current object instance
     */
    public function setBody($body) {
        if (!isset($body) || is_string($body)) {
            $this->body = $body;
        } elseif ($body instanceof Body) {
            $this->body = $body;
            $this->setAllHeaders($body->getHeaders());
        } elseif (is_callable($body)) {
            $this->body = $body;
        } else {
            throw new \InvalidArgumentException(
                sprintf('Body must be a string or valid callable; %s provided', gettype($body))
            );
        }

        return $this;
    }

}
