<?php
if (file_exists(__DIR__ . '/.env.php')) {
    require_once __DIR__ . '/.env.php';
}

if (!defined('DB_TYPE')) {
    define('DB_TYPE', 'sqlite');
    define('DB_HOST', '');
    define('DB_NAME', '');
    define('DB_USER', '');
    define('DB_PASS', '');
    define('SITE_NAME', '校园表白墙');
    define('THEME', 'default');
    define('CUSTOM_COLORS', '#66bb6a,#42a5f5');
    define('PURE_MODE', false);
    define('SENSITIVE_WORDS', '');
    define('THEME_AUTO_SWITCH', 'off');
    define('INSTALLED', false);
}

// 云控中心配置
define('CLOUD_CONTROL_URL', 'https://clearlove.kazx.top/yun.php');
define('ENCRYPTION_KEY', 'your_encryption_key_here');
define('APP_VERSION', '1.3.1');

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_MEDIA', 8);
define('MAX_CONTENT_LENGTH', 2000);
define('MAX_PREVIEW_LENGTH', 300);
