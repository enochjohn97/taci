<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Ensure tests run with APP_ENV=test so config/packages/test/*.yaml is loaded reliably
if (!isset($_SERVER['APP_ENV']) || $_SERVER['APP_ENV'] !== 'test') {
    // phpunit may not propagate server vars in some environments; force test env here
    $_SERVER['APP_ENV'] = 'test';
    $_ENV['APP_ENV'] = 'test';
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    // Prefer .env.test if present, otherwise fall back
    $envFile = file_exists(dirname(__DIR__).'/.env.test') ? dirname(__DIR__).'/.env.test' : dirname(__DIR__).'/.env';
    (new Dotenv())->bootEnv($envFile);
}

if (!empty($_SERVER['APP_DEBUG'])) {
    umask(0000);
}
