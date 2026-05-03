<?php
function getDB() {
    if (!INSTALLED) return null;
    try {
        if (DB_TYPE === 'mysql') {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } else {
            // SQLite
            $dbPath = __DIR__ . '/data.db';
            $pdo = new PDO("sqlite:$dbPath", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

function getPosts($db, $limit = 50) {
    $limit = (int)$limit;
    try {
        $stmt = $db->query("SELECT p.*, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count 
                              FROM posts p ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT $limit");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        $stmt = $db->query("SELECT p.*, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count 
                              FROM posts p ORDER BY p.created_at DESC LIMIT $limit");
        return $stmt->fetchAll();
    }
}

function getPost($db, $id) {
    $id = (int)$id;
    $stmt = $db->query("SELECT p.*, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count 
                          FROM posts p WHERE p.id = $id");
    return $stmt->fetch();
}

function getComments($db, $postId, $limit = null) {
    $postId = (int)$postId;
    if ($limit) {
        $limit = (int)$limit;
        $stmt = $db->query("SELECT * FROM comments WHERE post_id = $postId ORDER BY created_at DESC LIMIT $limit");
    } else {
        $stmt = $db->query("SELECT * FROM comments WHERE post_id = $postId ORDER BY created_at DESC");
    }
    return $stmt->fetchAll();
}

function getTopics($db) {
    $stmt = $db->query("SELECT * FROM topics ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getActiveAnnouncement($db) {
    $stmt = $db->query("SELECT * FROM announcements WHERE active = 1 ORDER BY created_at DESC LIMIT 1");
    return $stmt->fetch();
}

function formatTime($time) {
    $timestamp = strtotime($time . ' UTC');
    $diff = time() - $timestamp;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 604800) return floor($diff / 86400) . '天前';
    return date('Y-m-d', $timestamp);
}

function getRandomColor($seed) {
    $colors = [
        '#66bb6a', '#42a5f5', '#ab47bc', '#ffa726',
        '#26c6da', '#ef5350', '#ec407a', '#7e57c2'
    ];
    return $colors[crc32($seed) % count($colors)];
}

function convertToWebp($source, $quality = 85) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            return $source;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    $dest = pathinfo($source, PATHINFO_DIRNAME) . '/' . pathinfo($source, PATHINFO_FILENAME) . '.webp';
    imagewebp($image, $dest, $quality);
    imagedestroy($image);
    
    if ($dest !== $source) {
        unlink($source);
    }
    return $dest;
}

function recordVisit($db) {
    $ip = $db->quote($_SERVER['REMOTE_ADDR']);
    $date = $db->quote(date('Y-m-d'));
    if (DB_TYPE === 'mysql') {
        $db->exec("INSERT IGNORE INTO visits (ip, visit_date) VALUES ($ip, $date)");
    } else {
        // SQLite使用INSERT OR IGNORE
        $db->exec("INSERT OR IGNORE INTO visits (ip, visit_date) VALUES ($ip, $date)");
    }
}

function getVisitStats($db) {
    $today = $db->quote(date('Y-m-d'));
    $stmt = $db->query("SELECT COUNT(*) as count FROM visits WHERE visit_date = $today");
    $todayCount = $stmt->fetch()['count'];
    
    $weekAgo = $db->quote(date('Y-m-d', strtotime('-7 days')));
    $stmt = $db->query("SELECT COUNT(*) as count FROM visits WHERE visit_date >= $weekAgo");
    $weekCount = $stmt->fetch()['count'];
    
    $dailyData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = $db->quote(date('Y-m-d', strtotime("-$i days")));
        $stmt = $db->query("SELECT COUNT(*) as count FROM visits WHERE visit_date = $date");
        $dailyData[] = $stmt->fetch()['count'];
    }
    
    return [
        'today' => $todayCount,
        'week' => $weekCount,
        'daily' => $dailyData
    ];
}

function isFirstVisitToday($db) {
    $ip = $db->quote($_SERVER['REMOTE_ADDR']);
    $today = $db->quote(date('Y-m-d'));
    $stmt = $db->query("SELECT COUNT(*) as count FROM visits WHERE ip = $ip AND visit_date = $today");
    $count = $stmt->fetch()['count'];
    return $count == 0;
}

// 云控中心相关函数

// 加密函数
function encrypt($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// 解密函数
function decrypt($data) {
    $data = base64_decode($data);
    $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
    return openssl_decrypt($encrypted, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
}

// 云控中心API调用函数
function callCloudApi($action, $data = []) {
    $url = CLOUD_CONTROL_URL . '?action=' . $action;
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        $error = error_get_last();
        $errorMsg = 'API调用失败';
        if ($error && isset($error['message'])) {
            $errorMsg .= '：' . preg_replace('/^file_get_contents[^:]*: /', '', $error['message']);
        }
        return ['error' => $errorMsg];
    }
    
    return json_decode($result, true);
}

// 检查更新
function checkUpdate() {
    $result = callCloudApi('get_version');
    
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }
    
    if (isset($result['version'], $result['download_url'], $result['release_notes'])) {
        // 解密信息
        $result['download_url'] = decrypt($result['download_url']);
        $result['release_notes'] = decrypt($result['release_notes']);
        
        // 比较版本
        $currentVersion = APP_VERSION;
        $latestVersion = $result['version'];
        
        if (version_compare($latestVersion, $currentVersion, '>')) {
            return [
                'has_update' => true,
                'version' => $latestVersion,
                'download_url' => $result['download_url'],
                'release_notes' => $result['release_notes']
            ];
        } else {
            return [
                'has_update' => false,
                'version' => $latestVersion,
                'download_url' => $result['download_url'],
                'release_notes' => $result['release_notes']
            ];
        }
    } else {
        return [
            'has_update' => false,
            'version' => APP_VERSION,
            'download_url' => '',
            'release_notes' => ''
        ];
    }
}

// 获取公告
function getCloudAnnouncements() {
    $result = callCloudApi('get_announcements');
    
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }
    
    // 解密公告内容
    if (is_array($result)) {
        foreach ($result as &$announcement) {
            if (isset($announcement['content'])) {
                $announcement['content'] = decrypt($announcement['content']);
            }
        }
    }
    
    return $result;
}

// 获取敏感词库
function getCloudSensitiveWords() {
    $result = callCloudApi('get_sensitive_words');
    
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }
    
    if (isset($result['words'])) {
        return ['words' => decrypt($result['words'])];
    }
    
    return ['error' => '未获取到敏感词库'];
}

// 上报安装信息
function reportInstallation() {
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    callCloudApi('check', [
        'domain' => $domain,
        'version' => APP_VERSION
    ]);
}

// 检查更新
function checkForUpdates() {
    $result = callCloudApi('check_update', [
        'version' => APP_VERSION
    ]);
    
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }
    
    if (isset($result['version'])) {
        // 解密信息
        if (isset($result['download_url'])) {
            $result['download_url'] = decrypt($result['download_url']);
        }
        if (isset($result['release_notes'])) {
            $result['release_notes'] = decrypt($result['release_notes']);
        }
        
        // 比较版本
        $currentVersion = APP_VERSION;
        $latestVersion = $result['version'];
        
        if (version_compare($latestVersion, $currentVersion, '>')) {
            return [
                'has_update' => true,
                'version' => $latestVersion,
                'download_url' => $result['download_url'],
                'release_notes' => $result['release_notes']
            ];
        }
    }
    
    return ['has_update' => false];
}

function saveEnvConfig($config) {
    $envPath = __DIR__ . '/.env.php';
    $tempPath = $envPath . '.tmp.' . uniqid();
    
    $envContent = "<?php\n";
    
    $envContent .= "define('DB_TYPE', '" . addslashes($config['db_type'] ?? 'sqlite') . "');\n";
    
    if (($config['db_type'] ?? 'sqlite') === 'mysql') {
        $envContent .= "define('DB_HOST', '" . addslashes($config['db_host'] ?? '') . "');\n";
        $envContent .= "define('DB_NAME', '" . addslashes($config['db_name'] ?? '') . "');\n";
        $envContent .= "define('DB_USER', '" . addslashes($config['db_user'] ?? '') . "');\n";
        $envContent .= "define('DB_PASS', '" . addslashes($config['db_pass'] ?? '') . "');\n";
    }
    
    $envContent .= "define('SITE_NAME', '" . addslashes($config['site_name'] ?? '校园表白墙') . "');\n";
    $envContent .= "define('THEME', '" . addslashes($config['theme'] ?? 'default') . "');\n";
    $envContent .= "define('CUSTOM_COLORS', '" . addslashes($config['custom_colors'] ?? '#66bb6a,#42a5f5') . "');\n";
    $envContent .= "define('PURE_MODE', " . ($config['pure_mode'] ? 'true' : 'false') . ");\n";
    $envContent .= "define('SENSITIVE_WORDS', '" . addslashes($config['sensitive_words'] ?? '') . "');\n";
    $envContent .= "define('THEME_AUTO_SWITCH', '" . addslashes($config['theme_auto_switch'] ?? 'off') . "');\n";
    $envContent .= "define('BG_MUSIC_ENABLED', " . ($config['bg_music_enabled'] ? 'true' : 'false') . ");\n";
    $envContent .= "define('BG_MUSIC_FILE', '" . addslashes($config['bg_music_file'] ?? '') . "');\n";
    $envContent .= "define('BG_MUSIC_VOLUME', " . intval($config['bg_music_volume'] ?? 50) . ");\n";
    $envContent .= "define('CLOUD_AI_CHECK_ENABLED', " . (isset($config['cloud_ai_check_enabled']) && $config['cloud_ai_check_enabled'] ? 'true' : 'false') . ");\n";
    $envContent .= "define('INSTALLED', true);\n";
    
    if (file_put_contents($tempPath, $envContent) === false) {
        return false;
    }
    
    if (!rename($tempPath, $envPath)) {
        @unlink($tempPath);
        return false;
    }
    
    return true;
}

// 云端AI审核函数
function checkCloudAiContent($content) {
    // 默认安全响应
    $defaultSafeResponse = [
        'success' => true,
        'safe' => true,
        'message' => '✅ 内容安全，允许发布',
        'reason' => '本地检测通过',
        'suggestion' => ''
    ];
    
    // 检查是否启用云端AI审核
    if (!defined('CLOUD_AI_CHECK_ENABLED') || !CLOUD_AI_CHECK_ENABLED) {
        $defaultSafeResponse['message'] = '云端AI审核未启用';
        return $defaultSafeResponse;
    }
    
    // 检查云控中心URL是否配置
    if (!defined('CLOUD_CONTROL_URL') || empty(CLOUD_CONTROL_URL)) {
        $defaultSafeResponse['message'] = '云控中心未配置';
        return $defaultSafeResponse;
    }
    
    // 调用云控中心的AI审核接口
    $result = callCloudApi('ai_check', ['content' => $content]);
    
    if (isset($result['error'])) {
        error_log("[云端AI审核] 错误: " . $result['error']);
        // API调用失败，降级返回安全（不影响用户发帖），但附带原始错误信息
        $defaultSafeResponse['_cloud_raw'] = $result;
        return $defaultSafeResponse;
    }
    
    if (!isset($result['success']) || !$result['success']) {
        error_log("[云端AI审核] 返回失败: " . ($result['error'] ?? '未知错误'));
        $defaultSafeResponse['_cloud_raw'] = $result;
        return $defaultSafeResponse;
    }
    
    // 构建返回结果
    $isSafe = isset($result['safe']) && $result['safe'];
    
    return [
        'success' => true,
        'safe' => $isSafe,
        'message' => $isSafe ? '✅ 内容安全，允许发布' : '❌ 内容违规，将被拦截',
        'reason' => isset($result['reason']) ? $result['reason'] : ($isSafe ? '' : '云端AI判定违规'),
        'suggestion' => $isSafe ? '' : '请遵守社区规范，发布文明健康的内容。',
        '_cloud_raw' => $result,
    ];
}

