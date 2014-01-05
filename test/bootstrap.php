<?php

require __DIR__ . '/../autoload.php';

spl_autoload_register(function($class) {
    if (strpos($class, 'Arya\\Test\\') === 0) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = __DIR__ . "/test/{$class}.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});