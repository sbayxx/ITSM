<?php
/**
 * Root entry point for itsm.governit.dk
 * Routes /api/* requests to the backend, serves frontend for everything else
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route API requests to backend
if (strpos($uri, '/api') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/backend/public/index.php';
    exit;
}

// Serve existing static files
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// Everything else -> SPA index.html
readfile(__DIR__ . '/public/index.html');
