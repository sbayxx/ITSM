<?php
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../core/Env.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

Auth::init();

require_once __DIR__ . '/../middleware/CorsMiddleware.php';
$cors = new CorsMiddleware();
$cors->handle();

if (!isset($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

spl_autoload_register(function ($class) {
    $dirs = [
        __DIR__ . '/../controllers',
        __DIR__ . '/../models',
        __DIR__ . '/../services',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../services/MailService.php';

require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/RateLimiter.php';
Router::registerMiddleware('auth', Middleware::authenticate());
Router::registerMiddleware('admin', Middleware::admin());
Router::registerMiddleware('throttle', function () {
    $key = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!RateLimiter::attempt($key, 5, 900)) {
        $headers = RateLimiter::headers($key, 5);
        Response::json(['error' => 'Too many login attempts. Try again in 15 minutes.'], 429, $headers);
        return false;
    }
    return true;
});

require_once __DIR__ . '/../middleware/Authenticate.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';
require_once __DIR__ . '/../middleware/CsrfMiddleware.php';

require_once __DIR__ . '/../helpers/Pagination.php';
require_once __DIR__ . '/../helpers/SlaCalculator.php';
require_once __DIR__ . '/../helpers/PriorityMatrix.php';

require_once __DIR__ . '/../routes/api.php';

try {
    Router::dispatch();
} catch (PDOException $e) {
    Logger::error('Database error', ['message' => $e->getMessage()]);
    Response::error('Internal server error', 500);
} catch (Throwable $e) {
    Logger::error('Application error', ['message' => $e->getMessage()]);
    if (APP_DEBUG) {
        Response::error($e->getMessage(), 500);
    } else {
        Response::error('Internal server error', 500);
    }
}
