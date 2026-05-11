<?php
// Router for PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . '/public' . $url['path'];
    
    // If it's a real file, serve it
    if (is_file($file)) {
        return false;
    }
}

// Otherwise, route to public/index.php
require_once __DIR__ . '/public/index.php';
