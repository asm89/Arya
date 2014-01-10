<?php

namespace Arya\Sessions;

interface SessionHandler {

    /**
     * Does the specified $sessionId exist?
     *
     * @param string $sessionId
     * @param int $maxAge The maximum allowable age of the session in seconds
     * @return bool MUST return TRUE if the session exists (and is not expired) and FALSE if not
     */
    public function exists($sessionId, $maxAge);

    /**
     * Open the backend storage
     *
     * The session ID to be accessed is provided should the backend session storage require it.
     *
     * @param string $sessionId
     * @return bool MUST return TRUE on success or FALSE in the even of an error
     */
    public function open($sessionId);

    /**
     * Read a key-value array of data associated with the specified $sessionId
     *
     * @param string $sessionId
     * @return array MUST return an key-value associative array of stored session variables
     */
    public function read($sessionId);

    /**
     * Read a key-value array of data associated with the specified $sessionId
     *
     * @param string $sessionId
     * @param array $data;
     * @return bool MUST return TRUE for success and FALSE if writing failed.
     */
    public function write($sessionId, array $data);

    /**
     * Release any resources or locks associated with the session handler
     *
     * @return bool MUST return TRUE on successful close and FALSE if the operation failed
     */
    public function close();

    /**
     * Destroy the session identified by $sessionId
     *
     * @param string $sessionId
     * @param array $data;
     * @return bool MUST return FALSE if destruction fails and TRUE otherwise. A non-existent
     *              session MUST result in a TRUE return value.
     */
    public function destroy($sessionId);

    /**
     * Perform garbage collection of old sessions
     *
     * @param int $maxAge The maximum allowed age of a session in seconds
     * @return bool MUST return TRUE if no errors occurred and FALSE otherwise
     */
    public function gc($maxAge);

}
