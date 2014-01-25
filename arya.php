<?php

// --- DO NOT MODIFY THIS FILE! --- //

error_reporting(E_ALL | E_STRICT);

$__debug = isset($__debug) ? (bool) $__debug : FALSE;

/**
 * Parse errors cannot be handled inside the same file where they originate.
 * For this reason we have to include the application file externally here
 * so that our shutdown function can handle E_PARSE.
 */
register_shutdown_function(function() use ($__debug) {
    $fatals = array(
        E_ERROR,
        E_PARSE,
        E_USER_ERROR,
        E_CORE_ERROR,
        E_CORE_WARNING,
        E_COMPILE_ERROR,
        E_COMPILE_WARNING
    );

    $lastError = error_get_last();

    if ($lastError && in_array($lastError['type'], $fatals)) {
        if (headers_sent()) {
            return;
        }

        header_remove();
        header("HTTP/1.0 500 Internal Server Error");

        if ($__debug) {
            extract($lastError);
            $msg = sprintf("Fatal error: %s in %s on line %d", $message, $file, $line);
        } else {
            $msg = "Oops! Something went terribly wrong :(";
        }

        $msg = "<pre style=\"color:red;\">{$msg}</pre>";

        echo "<html><body><h1>500 Internal Server Error</h1><hr/>{$msg}</body></html>";
    }
});

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function($class) {
        if (0 === strpos($class, 'Arya\\')) {
            $class = str_replace('\\', '/', $class);
            $file = __DIR__ . "/lib/{$class}.php";
            if (file_exists($file)) {
                require $file;
            }
        }
    });
    
    require __DIR__ . '/vendor/Auryn/autoload.php';
}

if (isset($__application)) {
    require $__application;
} else {
    throw new RuntimeException(
        'No $__application file specified'
    );
}
