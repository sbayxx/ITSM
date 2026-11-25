<?php
// Credentials live exclusively in backend/.env. Env.php is required here (not
// only in index.php) so every entry point — index.php, migrate.php, one-off
// scripts — loads them the same way. No hardcoded secret fallbacks: a missing
// .env must fail loudly instead of silently using stale credentials.
require_once __DIR__ . '/../core/Env.php';

define('DB_HOST', Env::get('DB_HOST'));
define('DB_NAME', Env::get('DB_NAME'));
define('DB_USER', Env::get('DB_USER'));
define('DB_PASS', Env::get('DB_PASS'));
define('DB_CHARSET', Env::get('DB_CHARSET') ?: 'utf8mb4');
define('DB_PORT', (int)(Env::get('DB_PORT') ?: 3306));
