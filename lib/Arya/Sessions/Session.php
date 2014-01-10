<?php

namespace Arya\Sessions;

use Arya\Request;

class Session implements \ArrayAccess, \Iterator {

    const CACHE_NOCACHE = 'nocache';
    const CACHE_PRIVATE = 'private';
    const CACHE_PRIV_NO_EXP = 'private_no_expire';
    const CACHE_PUBLIC = 'public';

    private $request;
    private $handler;
    private $sessionId;
    private $data = array();
    private $isOpen = FALSE;
    private $needsSave = FALSE;
    private $wasAltered = FALSE;
    private $options = array(
        'cookie_name' => 'ASESSID',
        'cookie_domain' => '',
        'cookie_path' => '',
        'cookie_secure' => FALSE,
        'cookie_httponly' => TRUE,
        'cookie_lifetime' => 0,
        'check_referer' => '',
        'entropy_length' => 1024,
        'entropy_file' => NULL,
        'hash_function' => NULL,
        'cache_limiter' => self::CACHE_NOCACHE,
        'cache_expire' => 180,
        'gc_probability' => 1,
        'gc_divisor' => 100,
        'gc_max_lifetime' => 1440,
        'strict' => TRUE
    );

    public function __construct(Request $request, SessionHandler $handler = NULL) {
        $this->request = $request;
        $this->handler = $handler ?: new FileSessionHandler;
    }

    public function getOption($option) {
        $lcOption = strtolower($option);
        if (!isset($this->options[$lcOption])) {
            throw new \DomainException(
                sprintf('Unknown option: %s', $option)
            );
        }

        return $this->options[$lcOption];
    }

    public function setAllOptions(array $options) {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    public function setOption($option, $value) {
        switch (strtolower($option)) {
            case 'cookie_name':
                $this->setCookieName($value);
                break;
            case 'cookie_domain':
                $this->setCookieDomain($value);
                break;
            case 'cookie_path':
                $this->setCookiePath($value);
                break;
            case 'cookie_secure':
                $this->options['cookie_secure'] = (bool) $value;
                break;
            case 'cookie_http_only':
                $this->options['cookie_http_only'] = (bool) $value;
                break;
            case 'referer_check':
                $this->setRefererCheck($value);
                break;
            case 'hash_function':
                $this->setHashFunction($value);
                break;
            case 'cache_limiter':
                $this->setCacheLimiter($value);
                break;
            case 'cache_expire':
                $this->options['cache_expire'] = @intval($value) ?: 180;
                break;
            case 'gc_probability':
                $this->options['gc_probability'] = @intval($value) ?: 1;
                break;
            case 'gc_divisor':
                $this->options['gc_divisor'] = @intval($value) ?: 100;
                break;
            case 'gc_max_lifetime':
                $this->options['gc_max_lifetime'] = @intval($value) ?: 1440;
                break;
            case 'strict':
                $this->options['strict'] = (bool) $value;
            default:
                throw new \DomainException(
                    sprintf('Unkown session option: %s', $option)
                );
        }
    }

    private function setCookieName($value) {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                sprintf('Session cookie name expects a string: %s specified', gettype($value))
            );
        }

        $strlen = strlen($value);

        if ($strlen < 5) {
            throw new \InvalidArgumentException(
                "Session cookie name must be at least 8 bytes in size"
            );
        }

        $match = strspn($value, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

        if ($match < $strlen) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Non-alphanumeric character in session cookie name at index %d: %s',
                    $match,
                    $value[$match]
                )
            );
        }

        $this->options['cookie_name'] = $value;
    }

    private function setCookieDomain($value) {
        if (is_string($value)) {
            $this->options['cookie_domain'] = $value;
        } else {
            throw new \InvalidArgumentException(
                sprintf('Session cookie domain expects string; %s provided', gettype($value))
            );
        }
    }

    private function setCookiePath($value) {
        if (is_string($value)) {
            $this->options['cookie_path'] = $value;
        } else {
            throw new \InvalidArgumentException(
                sprintf('Session cookie path expects string; %s provided', gettype($value))
            );
        }
    }

    private function setRefererCheck($value) {
        if (is_string($value)) {
            $this->options['referer_check'] = $value;
        } else {
            throw new \InvalidArgumentException(
                sprintf(
                    'Session referer check constraint expects string; %s provided',
                    gettype($value)
                )
            );
        }
    }

    private function setHashFunction($value) {
        $lcValue = strtolower($value);

        switch ($lcValue) {
            case 'md5': break;
            case 'sha1': break;
            default:
                if (!(extension_loaded('hash') && in_array($lcValue, hash_algos()))) {
                    throw new \DomainException(
                        sprintf('Unkown session hash algo: %s', $value)
                    );
                }
        }

        $this->options['hash_function'] = $lcValue;
    }

    private function setCacheLimiter($value) {
        switch ($value) {
            case CACHE_NOCACHE: break;
            case CACHE_PRIVATE: break;
            case CACHE_PRIV_NO_EXP: break;
            case CACHE_PUBLIC: break;
            default:
                throw new \DomainException(
                    sprintf('Invalid session cache limiter value: %s', $value)
                );
        }

        $this->options['cache_limiter'] = $value;
    }

    public function has($field) {
        if (!$this->isOpen) {
            $this->open();
        }

        return isset($this->data[$field]);
    }

    private function setSessionId() {
        $cookieName = $this->options['cookie_name'];
        $sessionId = $this->request->hasCookie($cookieName)
            ? $this->getExistingSessionIdFromRequest($cookieName)
            : NULL;

        $this->sessionId = $sessionId ?: $this->generateSessionId();
    }

    private function getExistingSessionIdFromRequest($cookieName) {
        $checkReferer = $this->options['check_referer'];
        if ($checkReferer && !$this->matchesRefererHeader($checkReferer)) {
            return NULL;
        }

        $sessionId = $this->request->getCookie($cookieName);
        $isStrict = $this->options['strict'];
        $maxAge = $this->options['gc_max_lifetime'];

        return (!$isStrict || $this->handler->exists($sessionId, $maxAge))
            ? $sessionId
            : NULL;
    }

    private function matchesRefererHeader($checkReferer) {
        if (!$this->request->hasHeader('Referer')) {
            $isMatch = FALSE;
        } elseif ($checkReferer == $this->request->getHeader('Referer')) {
            $isMatch = TRUE;
        } else {
            $isMatch = FALSE;
        }

        return $isMatch;
    }

    private function generateSessionId() {
        $entropySource = $this->options['entropy_file'];
        $length = $this->options['entropy_length'];

        if ($entropySource) {
            $psrb = $this->getEntropyFromFile($entropySource, $length);
        } elseif (stripos(PHP_OS, 'WIN') === 0) {
            $psrb = $this->getWindowsEntropy($length);
        } else {
            $psrb = $this->getEntropyFromFile('/dev/urandom', $length);
        }

        if (!$psrb) {
            throw new \RuntimeException(
                'Failed retrieving pseudorandom bytes for session ID generation'
            );
        }

        if (!$algo = $this->options['hash_function']) {
            $algo = function_exists('hash') ? 'sha256' : 'sha1';
        }

        return ($algo === 'sha1') ? sha1($psrb) : hash($algo, $psrb);
    }

    private function getEntropyFromFile($file, $length) {
        return @file_get_contents($file, FALSE, null, -1, $length);
    }

    private function getWindowsEntropy($length) {
        if (function_exists('openssl_random_pseudo_bytes') && version_compare(PHP_VERSION, '5.3.4') >= 0) {
            $psrb = openssl_random_pseudo_bytes($length);
        } elseif (function_exists('mcrypt_create_iv') && version_compare(PHP_VERSION, '5.3.7') >= 0) {
            $psrb = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        } else {
            throw new \RuntimeException(
                '"ext/openssl"+PHP >= 5.3.4 or "ext/mcrypt"+PHP 5.3.7 required to generate Session IDs'
            );
        }

        return $psrb;
    }

    public function get($field) {
        if (!$this->isOpen) {
            $this->open();
        }

        if (isset($this->data[$field])) {
            return $this->data[$field];
        } else {
            throw new \DomainException(
                sprintf('Session field does not exist: %s', $field)
            );
        }
    }

    public function getAll() {
        if (!$this->isOpen) {
            $this->open();
        }

        return $this->data;
    }

    public function set($field, $value) {
        if (!$this->isOpen) {
            $this->open();
        }
        $this->needsSave = TRUE;
        $this->wasAltered = TRUE;
        $this->data[$field] = $value;
    }

    public function open() {
        if ($this->isOpen) {
            return;
        }

        if (!$this->sessionId) {
            $this->setSessionId();
        }

        if ($this->handler->open($this->sessionId)) {
            $this->data = $this->handler->read($this->sessionId);
            $this->isOpen = TRUE;
        } else {
            throw new SessionException(
                'Failed opening session'
            );
        }
    }

    public function save() {
        if (!$this->needsSave) {
            return;
        }

        if (!$this->isOpen) {
            $this->open();
        }

        if ($this->handler->write($this->sessionId, $this->data)) {
            $this->needsSave = FALSE;
        } else {
            throw new SessionException(
                'Failed saving session data'
            );
        }
    }

    public function close() {
        if (!$this->isOpen) {
            return;
        }

        $this->save();

        $gcProbability = $this->options['gc_probability'];
        if ($gcProbability > 0) {
            $this->collectGarbage($gcProbability);
        }

        $this->isOpen = FALSE;
        if (!$this->handler->close()) {
            throw new SessionException(
                'Failed closing session'
            );
        }
    }

    private function collectGarbage($gcProbability) {
        $gcDivisor = $this->options['gc_divisor'];
        $max = (int) ($gcDivisor / $gcProbability);
        if (rand(1, $max) === 1) {
            $maxLifetime = $this->options['gc_max_lifetime'];
            $this->handler->gc($maxLifetime);
        }
    }

    public function regenerate() {
        if ($oldSessionId = $this->sessionId) {
            $this->handler->close();
            $this->handler->destroy($oldSessionId);
            $this->data = array();
            $this->isOpen = FALSE;
        }

        $this->wasAltered = TRUE;
        $this->sessionId = $this->generateSessionId();
    }

    public function shouldSetCookie() {
        return $this->wasAltered;
    }

    public function getCookieElements() {
        return array(
            $this->options['cookie_name'],
            $this->sessionId,
            array(
                'domain' => $this->options['cookie_domain'],
                'path' => $this->options['cookie_path'],
                'expire' => $this->options['cookie_lifetime'],
                'secure' => $this->options['cookie_secure'],
                'httponly' => $this->options['cookie_httponly']
            )
        );
    }

    public function offsetSet($offset, $value) {
        $this->set($offset, $value);
    }

    public function offsetExists($offset) {
        return $this->has($offset);
    }

    public function offsetUnset($offset) {
        if (!$this->open) {
            $this->open();
        }
        if ($this->has($offset)) {
            unset($this->data[$offset]);
            $this->needsSave = TRUE;
            $this->wasAltered = TRUE;
        }
    }

    public function offsetGet($offset) {
        return $this->get($offset);
    }

    public function rewind() {
        reset($this->data);
    }

    public function current() {
        return current($this->data);
    }

    public function key() {
        return key($this->data);
    }

    public function next() {
        return next($this->data);
    }

    public function valid() {
        $key = key($this->data);

        return isset($this->data[$key]) || array_key_exists($key, $this->data);
    }

    public function __destruct() {
        $this->save();
        $this->close();
    }
}
