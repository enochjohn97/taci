<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load .env files manually if Dotenv not available
if (!isset($_ENV['APP_ENV'])) {
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0)
                continue;
            if (strpos($line, '=') === false)
                continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, ' "\'');
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Set default DATABASE_URL if not set (for demo)
if (!isset($_ENV['DATABASE_URL'])) {
    $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
    $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
    putenv('DATABASE_URL=sqlite:///:memory:');
}

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$request = Request::createFromGlobals();

try {
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (\Exception $e) {
    // Basic error page if something fails
    http_response_code(500);
    echo "<h1>System Error</h1><p>The system encountered an error. Please contact administrator.</p>";
    if (($_ENV['APP_DEBUG'] ?? true)) {
        echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
    }
}

