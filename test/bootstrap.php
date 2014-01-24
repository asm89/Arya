<?php

require __DIR__ . '/../autoload.php';
require __DIR__ . '/../vendor/Artax/autoload.php';

date_default_timezone_set('UTC');

spl_autoload_register(function($class) {
    if (strpos($class, 'Arya\\Test\\') === 0) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = __DIR__ . "/test/{$class}.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});

$serverCommand = sprintf(
    '%s -S %s:%d %s >/dev/null 2>&1 & echo $!',
    defined('PHP_BINARY') ? PHP_BINARY : 'php',
    WEB_SERVER_HOST,
    WEB_SERVER_PORT,
    WEB_SERVER_ROUTER
);

// Execute the command and store the process ID
$output = array();
exec($serverCommand, $output);
$pid = (int) $output[0];

printf(
    '%s[%s] Integration server started on %s:%d (pid: %d)%s',
    PHP_EOL,
    date('r'),
    WEB_SERVER_HOST,
    WEB_SERVER_PORT,
    $pid,
    PHP_EOL . PHP_EOL
);

// Kill the web server when the process ends
register_shutdown_function(function() use ($pid) {
    printf(
        '%s[%s] Killing integration server (pid: %d)%s',
        PHP_EOL,
        date('r'),
        $pid,
        PHP_EOL . PHP_EOL
    );
    exec('kill ' . $pid);
});