<?php

namespace Arya\Sessions;

class FileSessionHandler implements SessionHandler {

    private $storageDirectory;
    private $sessionId;
    private $fileHandle;
    private $isOpen = FALSE;

    public function __construct($dir = NULL) {
        $this->storageDirectory = $dir
            ? $this->setPath($dir)
            : sys_get_temp_dir() . '/aryasession';
    }

    private function setPath($path) {
        $isDir = is_dir($path);

        if ($isDir && !is_writable($path)) {
            throw new \RuntimeException(
                sprintf('Session storage path must be a writable directory: %s', $path)
            );
        } elseif (!$isDir && !@mkdir($path, 0777, TRUE)) {
            throw new \RuntimeException(
                sprintf('Failed creating session storage directory: %s', $path)
            );
        }

        return $path;
    }

    public function exists($sessionId, $maxAge) {
        $sessionFile = $this->generateSessionIdPath($sessionId);

        if (!file_exists($sessionFile)) {
            $exists = FALSE;
        } elseif (filemtime($sessionFile) + $maxAge < time()) {
            $exists = FALSE;
        } else {
            $exists = TRUE;
        }

        return $exists;
    }

    private function generateSessionIdPath($sessionId) {
        return sprintf("%s/%s/%s", $this->storageDirectory, $sessionId[0], $sessionId);
    }

    public function open($sessionId) {
        $this->sessionId = $sessionId;
        $path = $this->generateSessionIdPath($sessionId);
        $dir = dirname($path);

        if (!(is_dir($dir) || mkdir($dir, 0777, TRUE))) {
            return FALSE;
        }

        if ($handle = fopen($path, 'a+')) {
            rewind($handle);
            $this->fileHandle = $handle;
            flock($handle, LOCK_EX);
            $this->isOpen = TRUE;
        }

        return $this->isOpen;
    }

    public function close() {
        if ($this->isOpen) {
            flock($this->fileHandle, LOCK_UN);
            fclose($this->fileHandle);
        }

        return TRUE;
    }

    public function read($sessionId) {
        if ($sessionId !== $this->sessionId) {
            $this->close();
            $this->open($sessionId);
        }

        return ($data = stream_get_contents($this->fileHandle)) ? unserialize($data) : array();
    }

    public function write($sessionId, array $data) {
        if (!ftruncate($this->fileHandle, 0)) {
            return FALSE;
        }

        $serializedData = serialize($data);

        return (fwrite($this->fileHandle, $serializedData) === strlen($serializedData));
    }

    public function destroy($sessionId) {
        $path = $this->generateSessionIdPath($sessionId);

        return file_exists($path) ? unlink($path) : TRUE;
    }

    public function gc($maxAge) {
        $now = time();
        foreach (glob($this->storageDirectory) as $dir) {
            foreach (glob("{$dir}/*") as $subdir) {
                foreach (glob("{$subdir}/*") as $sessionFile) {
                    if (filemtime($sessionFile) + $maxAge < $now) {
                        unlink($sessionFile);
                    }
                }
            }
        }
    }

}
