<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'Parsedown.php';

$db = getDB();
if (!$db) {
    header('Location: install.php');
    exit;
}

$parsedown = new Parsedown();

session_start();

// 上报安装信息
reportInstallation();

// 检查更新
$updateInfo = checkUpdate();

// 获取公告
$cloudAnnouncements = getCloudAnnouncements();

$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
$page = $_GET['page'] ?? 'dashboard';

if (!$loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $quotedUser = $db->quote($username);
    $stmt = $db->query("SELECT * FROM admins WHERE username = $quotedUser");
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $loggedIn = true;
        
        // 登录成功后检查云端词库更新
        if (defined('PURE_MODE') && PURE_MODE) {
            $cloudWordsResult = getCloudSensitiveWords();
            if (!isset($cloudWordsResult['error']) && isset($cloudWordsResult['words'])) {
                $cloudWords = $cloudWordsResult['words'];
                $currentWords = defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '';
                
                // 如果云端词库与本地不同，则更新
                if ($cloudWords !== $currentWords) {
                    saveEnvConfig([
                        'db_type' => DB_TYPE,
                        'db_host' => defined('DB_HOST') ? DB_HOST : '',
                        'db_name' => defined('DB_NAME') ? DB_NAME : '',
                        'db_user' => defined('DB_USER') ? DB_USER : '',
                        'db_pass' => defined('DB_PASS') ? DB_PASS : '',
                        'site_name' => SITE_NAME,
                        'theme' => defined('THEME') ? THEME : 'default',
                        'custom_colors' => defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5',
                        'pure_mode' => true,
                        'sensitive_words' => $cloudWords,
                        'theme_auto_switch' => defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off',
                        'bg_music_enabled' => defined('BG_MUSIC_ENABLED') ? BG_MUSIC_ENABLED : false,
                        'bg_music_file' => defined('BG_MUSIC_FILE') ? BG_MUSIC_FILE : '',
                        'bg_music_volume' => defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50
                    ]);
                    
                    // 设置更新标志
                    $_SESSION['words_updated'] = true;
                }
            }
        }
    }
}

if (!$loggedIn) {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.staticfile.net/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.staticfile.net/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #e3f2fd 100%);
            min-height: 100vh;
        }
        .login-card {
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .input-focus:focus {
            animation: pulse 0.3s ease-in-out;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="login-card bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl p-10 max-w-md w-full border border-white/20">
        <div class="text-center mb-10">
            <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-blue-500 rounded-2xl mx-auto mb-6 flex items-center justify-center shadow-lg">
                <i class="fas fa-cog text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent mb-3">
                后台管理
            </h1>
            <p class="text-gray-500">请登录以继续</p>
        </div>
        <form method="POST">
            <div class="mb-6">
                <label class="block text-gray-700 mb-3 font-medium">管理员账号</label>
                <input type="text" name="username" required class="input-focus w-full px-5 py-4 border-2 border-gray-200 rounded-2xl focus:ring-4 focus:ring-green-500/20 focus:border-green-500 transition-all duration-300 outline-none">
            </div>
            <div class="mb-8">
                <label class="block text-gray-700 mb-3 font-medium">管理员密码</label>
                <input type="password" name="password" required class="input-focus w-full px-5 py-4 border-2 border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 outline-none">
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 rounded-2xl font-semibold hover:shadow-lg hover:scale-[1.02] transition-all duration-300">
                <i class="fas fa-sign-in-alt mr-2"></i>登录
            </button>
        </form>
    </div>
</body>
</html>
<?php
exit;
}

if ($page === 'logout') {
    session_destroy();
    header('Location: newadmin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_post'])) {
        $id = (int)$_POST['delete_post'];
        
        // 获取帖子的媒体文件
        $stmt = $db->query("SELECT media FROM posts WHERE id = $id");
        $post = $stmt->fetch();
        
        if ($post && $post['media']) {
            $mediaList = json_decode($post['media'], true);
            if (is_array($mediaList)) {
                foreach ($mediaList as $media) {
                    if (isset($media['file'])) {
                        $filePath = UPLOAD_DIR . '/' . $media['file'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                }
            }
        }
        
        $db->exec("DELETE FROM posts WHERE id = $id");
    }
    
    if (isset($_POST['toggle_pin'])) {
        $id = (int)$_POST['toggle_pin'];
        try {
            $stmt = $db->query("SELECT is_pinned FROM posts WHERE id = $id");
            $post = $stmt->fetch();
            $newPinStatus = empty($post['is_pinned']) ? 1 : 0;
            $db->exec("UPDATE posts SET is_pinned = $newPinStatus WHERE id = $id");
        } catch (PDOException $e) {
            if (DB_TYPE === 'sqlite') {
                $db->exec("ALTER TABLE posts ADD COLUMN is_pinned INTEGER DEFAULT 0");
            } else {
                $db->exec("ALTER TABLE posts ADD COLUMN is_pinned TINYINT(1) DEFAULT 0");
            }
            $db->exec("UPDATE posts SET is_pinned = 1 WHERE id = $id");
        }
    }
    
    if (isset($_POST['delete_comment'])) {
        $id = (int)$_POST['delete_comment'];
        $db->exec("DELETE FROM comments WHERE id = $id");
    }
    if (isset($_POST['edit_post'])) {
        $id = (int)$_POST['edit_post'];
        $content = $db->quote($_POST['content']);
        $db->exec("UPDATE posts SET content = $content WHERE id = $id");
    }
    if (isset($_POST['add_topic'])) {
        $name = $db->quote($_POST['topic_name']);
        if (DB_TYPE === 'mysql') {
            $db->exec("INSERT IGNORE INTO topics (name) VALUES ($name)");
        } else {
            $db->exec("INSERT OR IGNORE INTO topics (name) VALUES ($name)");
        }
    }
    if (isset($_POST['delete_topic'])) {
        $id = (int)$_POST['delete_topic'];
        $db->exec("DELETE FROM topics WHERE id = $id");
    }
    if (isset($_POST['add_announcement'])) {
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fileName = uniqid() . '.' . $ext;
            $uploadPath = UPLOAD_DIR . '/' . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $webpPath = convertToWebp($uploadPath);
                if ($webpPath) {
                    $image = pathinfo($webpPath, PATHINFO_BASENAME);
                }
            }
        }
        $content = $db->quote($_POST['content']);
        $img = $db->quote($image);
        $db->exec("INSERT INTO announcements (content, image) VALUES ($content, $img)");
    }
    if (isset($_POST['toggle_announcement'])) {
        $id = (int)$_POST['toggle_announcement'];
        $db->exec("UPDATE announcements SET active = NOT active WHERE id = $id");
    }
    if (isset($_POST['delete_announcement'])) {
        $id = (int)$_POST['delete_announcement'];
        $db->exec("DELETE FROM announcements WHERE id = $id");
    }
    
    if (isset($_POST['update_announcement'])) {
        $id = (int)$_POST['edit_announcement_id'];
        $content = $_POST['edit_announcement_content'];
        $image = '';
        
        if (isset($_FILES['edit_announcement_image']) && $_FILES['edit_announcement_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $imageName = uniqid() . '.' . pathinfo($_FILES['edit_announcement_image']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['edit_announcement_image']['tmp_name'], $uploadDir . '/' . $imageName);
            $image = $imageName;
        }
        
        if ($image) {
            $stmt = $db->prepare("UPDATE announcements SET content = ?, image = ? WHERE id = ?");
            $stmt->execute([$content, $image, $id]);
        } else {
            $stmt = $db->prepare("UPDATE announcements SET content = ? WHERE id = ?");
            $stmt->execute([$content, $id]);
        }
    }
    if (isset($_POST['update_site_name'])) {
        saveEnvConfig([
            'db_type' => DB_TYPE,
            'db_host' => defined('DB_HOST') ? DB_HOST : '',
            'db_name' => defined('DB_NAME') ? DB_NAME : '',
            'db_user' => defined('DB_USER') ? DB_USER : '',
            'db_pass' => defined('DB_PASS') ? DB_PASS : '',
            'site_name' => $_POST['site_name'],
            'theme' => defined('THEME') ? THEME : 'default',
            'custom_colors' => defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5',
            'pure_mode' => defined('PURE_MODE') ? PURE_MODE : false,
            'sensitive_words' => defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '',
            'theme_auto_switch' => defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off',
            'bg_music_enabled' => defined('BG_MUSIC_ENABLED') ? BG_MUSIC_ENABLED : false,
            'bg_music_file' => defined('BG_MUSIC_FILE') ? BG_MUSIC_FILE : '',
            'bg_music_volume' => defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50
        ]);
    }
    
    if (isset($_POST['update_theme'])) {
        saveEnvConfig([
            'db_type' => DB_TYPE,
            'db_host' => defined('DB_HOST') ? DB_HOST : '',
            'db_name' => defined('DB_NAME') ? DB_NAME : '',
            'db_user' => defined('DB_USER') ? DB_USER : '',
            'db_pass' => defined('DB_PASS') ? DB_PASS : '',
            'site_name' => SITE_NAME,
            'theme' => $_POST['theme'],
            'custom_colors' => $_POST['custom_colors'],
            'pure_mode' => defined('PURE_MODE') ? PURE_MODE : false,
            'sensitive_words' => defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '',
            'theme_auto_switch' => $_POST['theme_auto_switch'],
            'bg_music_enabled' => defined('BG_MUSIC_ENABLED') ? BG_MUSIC_ENABLED : false,
            'bg_music_file' => defined('BG_MUSIC_FILE') ? BG_MUSIC_FILE : '',
            'bg_music_volume' => defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50
        ]);
    }
    
    if (isset($_POST['update_pure_mode'])) {
        saveEnvConfig([
            'db_type' => DB_TYPE,
            'db_host' => defined('DB_HOST') ? DB_HOST : '',
            'db_name' => defined('DB_NAME') ? DB_NAME : '',
            'db_user' => defined('DB_USER') ? DB_USER : '',
            'db_pass' => defined('DB_PASS') ? DB_PASS : '',
            'site_name' => SITE_NAME,
            'theme' => defined('THEME') ? THEME : 'default',
            'custom_colors' => defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5',
            'pure_mode' => isset($_POST['pure_mode']),
            'sensitive_words' => $_POST['sensitive_words'],
            'theme_auto_switch' => defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off',
            'bg_music_enabled' => defined('BG_MUSIC_ENABLED') ? BG_MUSIC_ENABLED : false,
            'bg_music_file' => defined('BG_MUSIC_FILE') ? BG_MUSIC_FILE : '',
            'bg_music_volume' => defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50
        ]);
    }
    
    if (isset($_POST['update_bg_music'])) {
        $bgMusicFile = defined('BG_MUSIC_FILE') ? BG_MUSIC_FILE : '';
        
        if (isset($_FILES['bg_music_file']) && $_FILES['bg_music_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $musicName = uniqid() . '.' . pathinfo($_FILES['bg_music_file']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['bg_music_file']['tmp_name'], $uploadDir . '/' . $musicName);
            $bgMusicFile = $musicName;
        }
        
        saveEnvConfig([
            'db_type' => DB_TYPE,
            'db_host' => defined('DB_HOST') ? DB_HOST : '',
            'db_name' => defined('DB_NAME') ? DB_NAME : '',
            'db_user' => defined('DB_USER') ? DB_USER : '',
            'db_pass' => defined('DB_PASS') ? DB_PASS : '',
            'site_name' => SITE_NAME,
            'theme' => defined('THEME') ? THEME : 'default',
            'custom_colors' => defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5',
            'pure_mode' => defined('PURE_MODE') ? PURE_MODE : false,
            'sensitive_words' => defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '',
            'theme_auto_switch' => defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off',
            'bg_music_enabled' => isset($_POST['bg_music_enabled']),
            'bg_music_file' => $bgMusicFile,
            'bg_music_volume' => intval($_POST['bg_music_volume'] ?? 50)
        ]);
    }
    
    if (isset($_POST['delete_bg_music'])) {
        saveEnvConfig([
            'db_type' => DB_TYPE,
            'db_host' => defined('DB_HOST') ? DB_HOST : '',
            'db_name' => defined('DB_NAME') ? DB_NAME : '',
            'db_user' => defined('DB_USER') ? DB_USER : '',
            'db_pass' => defined('DB_PASS') ? DB_PASS : '',
            'site_name' => SITE_NAME,
            'theme' => defined('THEME') ? THEME : 'default',
            'custom_colors' => defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5',
            'pure_mode' => defined('PURE_MODE') ? PURE_MODE : false,
            'sensitive_words' => defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '',
            'theme_auto_switch' => defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off',
            'bg_music_enabled' => false,
            'bg_music_file' => '',
            'bg_music_volume' => 50
        ]);
    }
    
    // 云端AI审核开关保存
    if (isset($_POST['update_cloud_ai'])) {
        saveEnvConfig([
            'db_type' => DB_TYPE,
            'db_host' => defined('DB_HOST') ? DB_HOST : '',
            'db_name' => defined('DB_NAME') ? DB_NAME : '',
            'db_user' => defined('DB_USER') ? DB_USER : '',
            'db_pass' => defined('DB_PASS') ? DB_PASS : '',
            'site_name' => SITE_NAME,
            'theme' => defined('THEME') ? THEME : 'default',
            'custom_colors' => defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5',
            'pure_mode' => defined('PURE_MODE') ? PURE_MODE : false,
            'sensitive_words' => defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '',
            'theme_auto_switch' => defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off',
            'bg_music_enabled' => defined('BG_MUSIC_ENABLED') ? BG_MUSIC_ENABLED : false,
            'bg_music_file' => defined('BG_MUSIC_FILE') ? BG_MUSIC_FILE : '',
            'bg_music_volume' => defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50,
            'cloud_ai_check_enabled' => isset($_POST['cloud_ai_check_enabled'])
        ]);
    }
}

$visitStats = getVisitStats($db);
$posts = getPosts($db, 100);
$topics = getTopics($db);

$stmt = $db->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $stmt->fetchAll();

$stmt = $db->query("SELECT COUNT(*) FROM posts");
$totalPosts = $stmt->fetchColumn();

// 计算网站总占用空间和文件类型分布
function calculateStorageUsage($dir) {
    $totalSize = 0;
    $fileTypes = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size = $file->getSize();
            $totalSize += $size;
            
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (!isset($fileTypes[$extension])) {
                $fileTypes[$extension] = 0;
            }
            $fileTypes[$extension] += $size;
        }
    }
    
    return [
        'totalSize' => $totalSize,
        'fileTypes' => $fileTypes
    ];
}

// 格式化文件大小
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}

// 计算存储空间
$storage = calculateStorageUsage(__DIR__);
$totalStorage = $storage['totalSize'];
$fileTypes = $storage['fileTypes'];
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.staticfile.net/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.staticfile.net/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdn.staticfile.net/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #e3f2fd 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 4px 0 30px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .sidebar-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(102, 187, 106, 0.3);
        }
        
        .sidebar-title {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-nav {
            padding: 24px 16px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            margin-bottom: 8px;
            border-radius: 12px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
            transform: scaleY(0);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-item:hover {
            background: rgba(102, 187, 106, 0.1);
            transform: translateX(4px);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, rgba(102, 187, 106, 0.15) 0%, rgba(66, 165, 245, 0.15) 100%);
            color: #2d3748;
            font-weight: 600;
        }
        
        .nav-item.active::before {
            transform: scaleY(1);
        }
        
        .nav-item i {
            width: 24px;
            margin-right: 16px;
            font-size: 18px;
        }
        
        .nav-item.logout {
            color: #e53e3e;
            margin-top: 24px;
        }
        
        .nav-item.logout:hover {
            background: rgba(229, 62, 62, 0.1);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 32px;
            min-height: 100vh;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .page-header {
            margin-bottom: 32px;
            animation: fadeInDown 0.6s ease-out;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 16px;
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        .stat-icon.green {
            background: linear-gradient(135deg, rgba(102, 187, 106, 0.2) 0%, rgba(102, 187, 106, 0.1) 100%);
            color: #66bb6a;
        }
        
        .stat-icon.blue {
            background: linear-gradient(135deg, rgba(66, 165, 245, 0.2) 0%, rgba(66, 165, 245, 0.1) 100%);
            color: #42a5f5;
        }
        
        .stat-icon.purple {
            background: linear-gradient(135deg, rgba(156, 39, 176, 0.2) 0%, rgba(156, 39, 176, 0.1) 100%);
            color: #9c27b0;
        }
        
        .stat-icon.yellow {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.2) 0%, rgba(255, 193, 7, 0.1) 100%);
            color: #ffc107;
        }
        
        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #1a202c;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            animation: fadeInUp 0.6s ease-out;
        }
        
        .card-header {
            font-size: 20px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
        }
        
        .card-header i {
            margin-right: 12px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(102, 187, 106, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 187, 106, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(229, 62, 62, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(229, 62, 62, 0.4);
        }
        
        .btn-secondary {
            background: rgba(113, 128, 150, 0.1);
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: rgba(113, 128, 150, 0.2);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #66bb6a;
            box-shadow: 0 0 0 4px rgba(102, 187, 106, 0.1);
        }
        
        .form-textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            resize: vertical;
            min-height: 120px;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #66bb6a;
            box-shadow: 0 0 0 4px rgba(102, 187, 106, 0.1);
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 32px;
            background: #e2e8f0;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .toggle-switch.active {
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
        }
        
        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            top: 4px;
            left: 4px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .toggle-switch.active::after {
            transform: translateX(28px);
        }
        
        .theme-option {
            position: relative;
            cursor: pointer;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .theme-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .theme-option.selected {
            border-color: #66bb6a;
            box-shadow: 0 0 0 3px rgba(102, 187, 106, 0.2);
        }
        
        .theme-preview {
            height: 48px;
            border-radius: 8px 8px 0 0;
        }
        
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }
        
        @media (max-width: 1200px) {
            .theme-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .theme-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .theme-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .page-loader.active {
            opacity: 1;
            visibility: visible;
        }
        
        .loader-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top-color: #66bb6a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .loader-text {
            margin-top: 20px;
            color: #4a5568;
            font-size: 14px;
            font-weight: 500;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .mobile-menu-btn:hover {
            transform: scale(1.05);
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .overlay.active {
            display: block;
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 80px 16px 16px;
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .stat-value {
                font-size: 28px;
            }
        }
        
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .theme-option {
            height: 100px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 3px solid transparent;
        }
        
        .theme-option:hover {
            transform: scale(1.05);
        }
        
        .theme-option.selected {
            border-color: #66bb6a;
            box-shadow: 0 8px 24px rgba(102, 187, 106, 0.3);
        }
        
        .theme-preview {
            height: 100%;
            width: 100%;
        }
        
        .post-item {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .post-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        
        .announcement-item {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal.active {
            display: flex;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 16px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- 页面加载动画 -->
    <div class="page-loader active" id="pageLoader">
        <div class="loader-spinner"></div>
        <div class="loader-text">加载中...</div>
    </div>
    
    <!-- 移动端菜单按钮 -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars text-xl text-gray-700" id="menuIcon"></i>
    </button>
    
    <!-- 侧边栏覆盖层 -->
    <div class="overlay" onclick="toggleSidebar()"></div>
    
    <!-- 侧边栏 -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-cog text-white text-2xl"></i>
            </div>
            <h1 class="sidebar-title">后台管理</h1>
        </div>
        <nav class="sidebar-nav">
            <a href="?page=dashboard" class="nav-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>首页</span>
            </a>
            <a href="?page=config" class="nav-item <?php echo $page === 'config' ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i>
                <span>网站配置</span>
            </a>
            <a href="?page=posts" class="nav-item <?php echo $page === 'posts' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>帖子管理</span>
            </a>
            <a href="?page=announcements" class="nav-item <?php echo $page === 'announcements' ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i>
                <span>公告与话题</span>
            </a>
            <a href="?page=about" class="nav-item <?php echo $page === 'about' ? 'active' : ''; ?>">
                <i class="fas fa-info-circle"></i>
                <span>关于系统</span>
            </a>
            <a href="?page=logout" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>退出登录</span>
            </a>
        </nav>
    </aside>
    
    <!-- 主内容区 -->
    <main class="main-content">
        <?php if ($page === 'dashboard'): ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-home"></i>
                    数据概览
                </h1>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card" style="animation-delay: 0.1s;">
                    <div class="stat-icon green">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-label">帖子总数</div>
                    <div class="stat-value"><?php echo $totalPosts; ?></div>
                </div>
                
                <div class="stat-card" style="animation-delay: 0.2s;">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-label">今日访问IP</div>
                    <div class="stat-value"><?php echo $visitStats['today']; ?></div>
                </div>
                
                <div class="stat-card" style="animation-delay: 0.3s;">
                    <div class="stat-icon purple">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-label">近7日访问IP</div>
                    <div class="stat-value"><?php echo $visitStats['week']; ?></div>
                </div>
                
                <div class="stat-card" style="animation-delay: 0.4s;">
                    <div class="stat-icon yellow">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="stat-label">网站总占用空间</div>
                    <div class="stat-value"><?php echo formatFileSize($totalStorage); ?></div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar text-blue-500"></i>
                        近7日访问统计
                    </div>
                    <canvas id="visitChart" height="300"></canvas>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie text-yellow-500"></i>
                        文件类型占用比例
                    </div>
                    <canvas id="storageChart" height="300"></canvas>
                </div>
            </div>
            
        <?php elseif ($page === 'config'): ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-sliders-h"></i>
                    网站配置
                </h1>
            </div>
            
            <!-- 网站名称配置 -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-globe text-green-500"></i>
                    网站名称
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">网站名称</label>
                        <input type="text" name="site_name" value="<?php echo SITE_NAME; ?>" class="form-input">
                    </div>
                    <button type="submit" name="update_site_name" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>保存配置
                    </button>
                </form>
            </div>
            
            <!-- 主题配置 -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-palette text-purple-500"></i>
                    配色主题
                </div>
                <form method="POST" id="themeForm">
                    <div class="form-group">
                        <label class="form-label">选择主题</label>
                        <div class="theme-grid">
                            <?php
                            $regularThemes = [
                                'default' => ['name' => '默认主题', 'colors' => '#66bb6a,#42a5f5'],
                                'pink' => ['name' => '粉色主题', 'colors' => '#ec407a,#ff80ab'],
                                'purple' => ['name' => '紫色主题', 'colors' => '#7e57c2,#ab47bc'],
                                'orange' => ['name' => '橙色主题', 'colors' => '#ffa726,#ff7043'],
                                'red' => ['name' => '红色主题', 'colors' => '#ef5350,#e53935'],
                                'teal' => ['name' => '青色主题', 'colors' => '#26c6da,#00acc1'],
                                'amber' => ['name' => '琥珀主题', 'colors' => '#ffca28,#ff9800'],
                                'indigo' => ['name' => '靛蓝主题', 'colors' => '#3f51b5,#5c6bc0'],
                                'deep-purple' => ['name' => '深紫主题', 'colors' => '#5e35b1,#4527a0'],
                                'light-blue' => ['name' => '浅蓝主题', 'colors' => '#29b6f6,#03a9f4'],
                                'lime' => ['name' => '青柠主题', 'colors' => '#cddc39,#8bc34a'],
                                'cyan' => ['name' => '蓝绿色主题', 'colors' => '#00bcd4,#0097a7'],
                                'teal-green' => ['name' => '青绿主题', 'colors' => '#66bb6a,#43a047'],
                                'purple-pink' => ['name' => '紫粉主题', 'colors' => '#9c27b0,#e91e63'],
                                'blue-indigo' => ['name' => '蓝靛主题', 'colors' => '#1e88e5,#3949ab'],
                                'orange-red' => ['name' => '橙红主题', 'colors' => '#ff9800,#f44336'],
                                'green-teal' => ['name' => '绿青主题', 'colors' => '#4caf50,#009688'],
                                'blue-cyan' => ['name' => '蓝青主题', 'colors' => '#2196f3,#00bcd4'],
                                'purple-indigo' => ['name' => '紫靛主题', 'colors' => '#7b1fa2,#303f9f'],
                                'pink-red' => ['name' => '粉红主题', 'colors' => '#e91e63,#c2185b'],
                                'amber-orange' => ['name' => '琥珀橙主题', 'colors' => '#ffb300,#ff7043'],
                                'teal-cyan' => ['name' => '青蓝主题', 'colors' => '#26a69a,#00acc1'],
                                'indigo-purple' => ['name' => '靛紫主题', 'colors' => '#536dfe,#7b1fa2'],
                                'green-lime' => ['name' => '绿柠主题', 'colors' => '#4caf50,#cddc39'],
                                'custom' => ['name' => '自定义主题', 'colors' => '']
                            ];
                            
                            $holidayThemes = [
                                'christmas' => ['name' => '圣诞主题', 'colors' => '#e53935,#43a047'],
                                'valentine' => ['name' => '情人节主题', 'colors' => '#e91e63,#c2185b'],
                                'spring' => ['name' => '春节主题', 'colors' => '#e53935,#ffb300'],
                                'mayday' => ['name' => '劳动节主题', 'colors' => '#4caf50,#81c784'],
                                'children' => ['name' => '儿童节主题', 'colors' => '#ff9800,#ffb74d'],
                                'midautumn' => ['name' => '中秋节主题', 'colors' => '#9c27b0,#ba68c8'],
                                'national' => ['name' => '国庆节主题', 'colors' => '#e53935,#ff5722'],
                                'qingming' => ['name' => '清明节主题', 'colors' => '#4caf50,#81c784'],
                                'dragon' => ['name' => '端午节主题', 'colors' => '#e53935,#ff9800'],
                                'qixi' => ['name' => '七夕节主题', 'colors' => '#e91e63,#9c27b0']
                            ];
                            
                            $currentTheme = defined('THEME') ? THEME : 'default';
                            ?>
                            
                            <div id="regularThemesContainer">
                            <?php
                            $themeCount = 0;
                            foreach ($regularThemes as $key => $theme) {
                                if ($key === 'custom') continue;
                                $themeCount++;
                                $isActive = $currentTheme === $key;
                                $style = 'background: linear-gradient(135deg, ' . $theme['colors'] . ');';
                                $isHidden = $themeCount > 10;
                            ?>
                            <div class="theme-option <?php echo $isActive ? 'selected' : ''; ?> <?php echo $isHidden ? 'hidden' : ''; ?>" data-theme="<?php echo $key; ?>" data-colors="<?php echo $theme['colors']; ?>" onclick="selectTheme(this)">
                                <div class="theme-preview" style="<?php echo $style; ?>"></div>
                                <div style="padding: 8px; text-align: center; font-size: 12px; font-weight: 500;"><?php echo $theme['name']; ?></div>
                            </div>
                            <?php } ?>
                            </div>
                            
                            <?php if (count($regularThemes) > 11): ?>
                            <div style="grid-column: 1 / -1; margin-top: 8px;">
                                <button type="button" id="toggleThemesBtn" class="btn btn-secondary" style="width: 100%;" onclick="toggleAllThemes()">
                                    <i class="fas fa-chevron-down mr-2"></i><span>展开全部</span>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <div style="grid-column: 1 / -1; margin-top: 16px; border: 2px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                                <div style="background: #f7fafc; padding: 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;" onclick="toggleHolidayThemes()">
                                    <span style="font-weight: 500; color: #4a5568;">节日主题</span>
                                    <i id="holidayThemeIcon" class="fas fa-chevron-down" style="color: #718096; transition: transform 0.3s;"></i>
                                </div>
                                <div id="holidayThemesContainer" style="display: none; padding: 12px;" class="theme-grid">
                                    <?php foreach ($holidayThemes as $key => $theme) {
                                        $isActive = $currentTheme === $key;
                                        $style = 'background: linear-gradient(135deg, ' . $theme['colors'] . ');';
                                    ?>
                                    <div class="theme-option <?php echo $isActive ? 'selected' : ''; ?>" data-theme="<?php echo $key; ?>" data-colors="<?php echo $theme['colors']; ?>" onclick="selectTheme(this)">
                                        <div class="theme-preview" style="<?php echo $style; ?>"></div>
                                        <div style="padding: 8px; text-align: center; font-size: 12px; font-weight: 500;"><?php echo $theme['name']; ?></div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">自定义颜色（选择自定义主题后生效）</label>
                        <div style="display: flex; gap: 16px;">
                            <div style="flex: 1;">
                                <label style="font-size: 12px; color: #718096; margin-bottom: 4px; display: block;">主色</label>
                                <input type="color" id="customColor1" value="<?php echo defined('CUSTOM_COLORS') ? explode(',', CUSTOM_COLORS)[0] : '#66bb6a'; ?>" class="form-input" style="height: 50px; padding: 4px; cursor: pointer;" onchange="updateCustomColors()">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 12px; color: #718096; margin-bottom: 4px; display: block;">辅色</label>
                                <input type="color" id="customColor2" value="<?php echo defined('CUSTOM_COLORS') ? (explode(',', CUSTOM_COLORS)[1] ?? '#42a5f5') : '#42a5f5'; ?>" class="form-input" style="height: 50px; padding: 4px; cursor: pointer;" onchange="updateCustomColors()">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">主题自动切换</label>
                        <select name="theme_auto_switch" class="form-input">
                            <option value="off" <?php echo (defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') === 'off' ? 'selected' : ''; ?>>不切换</option>
                            <option value="daily" <?php echo (defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') === 'daily' ? 'selected' : ''; ?>>每天切换</option>
                            <option value="random" <?php echo (defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') === 'random' ? 'selected' : ''; ?>>每次随机</option>
                        </select>
                    </div>
                    <input type="hidden" name="theme" id="selectedTheme" value="<?php echo defined('THEME') ? THEME : 'default'; ?>">
                    <input type="hidden" name="custom_colors" id="customColors" value="<?php echo defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5'; ?>">
                    <button type="submit" name="update_theme" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>保存主题
                    </button>
                </form>
            </div>
            
            <!-- 纯净模式配置 -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-shield-alt text-red-500"></i>
                    纯净模式
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">开启纯净模式</label>
                        <div class="toggle-switch <?php echo defined('PURE_MODE') && PURE_MODE ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <input type="checkbox" name="pure_mode" value="1" <?php echo defined('PURE_MODE') && PURE_MODE ? 'checked' : ''; ?> style="display: none;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">敏感词（空格分隔）</label>
                        <textarea name="sensitive_words" class="form-textarea"><?php echo defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : ''; ?></textarea>
                    </div>
                    <button type="button" onclick="useCloudSensitiveWords()" class="btn btn-secondary mr-2">
                        <i class="fas fa-cloud-download-alt mr-2"></i>使用云端词库
                    </button>
                    <button type="submit" name="update_pure_mode" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>保存配置
                    </button>
                </form>
            </div>
            
            <!-- 背景音乐配置 -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-music text-blue-500"></i>
                    背景音乐
                </div>
                <form method="POST" enctype="multipart/form-data" id="musicForm">
                    <div class="form-group">
                        <label class="form-label">开启背景音乐</label>
                        <div class="toggle-switch <?php echo defined('BG_MUSIC_ENABLED') && BG_MUSIC_ENABLED ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <input type="checkbox" name="bg_music_enabled" value="1" <?php echo defined('BG_MUSIC_ENABLED') && BG_MUSIC_ENABLED ? 'checked' : ''; ?> style="display: none;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">默认音量（0-100）</label>
                        <input type="range" name="bg_music_volume" min="0" max="100" value="<?php echo defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50; ?>" class="form-input" style="padding: 0;">
                        <div style="text-align: center; margin-top: 8px;">
                            <span id="volumeValue"><?php echo defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50; ?>%</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">上传音乐文件</label>
                        <input type="file" name="bg_music_file" accept="audio/*" class="form-input" onchange="uploadMusic(this)">
                        <div class="progress-bar" id="musicProgress" style="display: none;">
                            <div class="progress-fill" id="musicProgressFill" style="width: 0%;"></div>
                        </div>
                    </div>
                    <?php if (defined('BG_MUSIC_FILE') && BG_MUSIC_FILE): ?>
                    <div class="form-group">
                        <label class="form-label">当前音乐</label>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-music text-blue-500"></i>
                            <span><?php echo BG_MUSIC_FILE; ?></span>
                            <button type="submit" name="delete_bg_music" class="btn btn-danger" style="padding: 8px 16px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" name="update_bg_music" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>保存配置
                    </button>
                </form>
            </div>
            
            <!-- 云端AI审核设置 -->
            <div class="settings-card">
                <div class="settings-card-header">
                    <i class="fas fa-robot text-purple-500"></i>
                    云端AI内容审核
                    <span style="margin-left: 8px; padding: 2px 10px; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; border-radius: 12px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; vertical-align: middle;">Beta</span>
                </div>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 14px; color: #666;">
                        <i class="fas fa-info-circle mr-2" style="color: #3b82f6;"></i>
                        开启后，用户发帖时系统会自动将内容发送至云控中心进行AI智能审核。需确保云控中心已配置AI服务。
                    </p>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">启用云端AI审核</label>
                        <div class="toggle-switch <?php echo (defined('CLOUD_AI_CHECK_ENABLED') && CLOUD_AI_CHECK_ENABLED) ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <input type="checkbox" name="cloud_ai_check_enabled" value="1" <?php echo (defined('CLOUD_AI_CHECK_ENABLED') && CLOUD_AI_CHECK_ENABLED) ? 'checked' : ''; ?> style="display: none;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">当前状态</label>
                        <div style="padding: 12px; background: <?php echo (defined('CLOUD_AI_CHECK_ENABLED') && CLOUD_AI_CHECK_ENABLED) ? '#d4edda' : '#f8f9fa'; ?>; border-radius: 6px; font-size: 14px;">
                            <?php if (defined('CLOUD_AI_CHECK_ENABLED') && CLOUD_AI_CHECK_ENABLED): ?>
                                <span style="color: #155724;"><i class="fas fa-check-circle mr-2"></i>已启用 - 用户发帖将通过云端AI审核</span>
                            <?php else: ?>
                                <span style="color: #666;"><i class="fas fa-circle mr-2"></i>未启用 - 仅使用本地敏感词检测</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" name="update_cloud_ai" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>保存AI审核设置
                    </button>
                </form>
                
                <!-- AI测试区域 -->
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                    <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #1a202c;">
                        <i class="fas fa-vial mr-2" style="color: #9333ea;"></i>测试AI审核效果
                    </h4>
                    <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                        <input type="text" id="cloudAiTestContent" placeholder="输入要测试的内容..." 
                               style="flex: 1; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <button onclick="testCloudAi()" 
                                style="padding: 10px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
                            <i class="fas fa-play mr-2"></i>开始测试
                        </button>
                    </div>
                    <div id="cloudAiTestResult" style="display: none;"></div>
                </div>
            </div>
            
        <?php elseif ($page === 'posts'): ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-list"></i>
                    帖子管理
                </h1>
            </div>
            
            <?php foreach ($posts as $post): ?>
            <div class="post-item">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                    <div style="flex: 1;">
                        <div style="color: #718096; font-size: 14px; margin-bottom: 8px;">
                            <i class="fas fa-clock mr-2"></i><?php echo $post['created_at']; ?>
                            <?php if ($post['topic']): ?>
                            <span style="margin-left: 16px;"><i class="fas fa-tag mr-2"></i><?php echo htmlspecialchars($post['topic']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($post['is_pinned'])): ?>
                            <span style="margin-left: 16px; color: #f59e0b;"><i class="fas fa-thumbtack mr-2"></i>已置顶</span>
                            <?php endif; ?>
                        </div>
                        <div style="color: #2d3748; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                    </div>
                    <div style="display: flex; gap: 8px; margin-left: 16px;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="toggle_pin" value="<?php echo $post['id']; ?>">
                            <button type="submit" class="btn <?php echo !empty($post['is_pinned']) ? 'btn-warning' : 'btn-secondary'; ?>" style="padding: 8px 16px;" title="<?php echo !empty($post['is_pinned']) ? '取消置顶' : '置顶'; ?>">
                                <i class="fas fa-thumbtack"></i>
                            </button>
                        </form>
                        <button onclick="editPost(<?php echo $post['id']; ?>, '<?php echo addslashes($post['content']); ?>')" class="btn btn-secondary" style="padding: 8px 16px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这个帖子吗？')">
                            <input type="hidden" name="delete_post" value="<?php echo $post['id']; ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 8px 16px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
        <?php elseif ($page === 'about'): ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-info-circle"></i>
                    关于系统
                </h1>
            </div>
            
            <div class="card">
                <div style="text-align: center; padding: 40px 0;">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%); border-radius: 20px; margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(102, 187, 106, 0.3);">
                        <i class="fas fa-heart text-white text-3xl"></i>
                    </div>
                    <h2 style="font-size: 28px; font-weight: 700; color: #1a202c; margin-bottom: 16px;">ClearLove 表白墙</h2>
                    <p style="color: #718096; margin-bottom: 24px;">仰望星辰工作室，夏日之瓜，出品</p>
                    
                    <div style="margin-bottom: 24px;">
                        <a href="https://clearlove.kazx.top/" target="_blank" class="btn btn-primary" style="margin-right: 12px;">
                            <i class="fas fa-globe mr-2"></i>访问官网
                        </a>
                        <a href="https://github.com/FQH666666/clearlove-lovecards" target="_blank" class="btn btn-secondary">
                            <i class="fab fa-github mr-2"></i>GitHub
                        </a>
                    </div>
                    
                    <div style="background: rgba(102, 187, 106, 0.1); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                        <p style="color: #4a5568; margin-bottom: 8px;">当前版本</p>
                        <p style="font-size: 24px; font-weight: 700; color: #1a202c;">v<?php echo APP_VERSION; ?></p>
                    </div>
                    
                    <button onclick="checkUpdate()" class="btn btn-primary" style="margin-bottom: 24px;">
                        <i class="fas fa-sync-alt mr-2"></i>检查更新
                    </button>
                    
                    <div style="margin-top: 32px;">
                        <button onclick="showDonation()" class="btn btn-danger">
                            <i class="fas fa-heart mr-2"></i>捐赠开发者
                        </button>
                    </div>
                </div>
            </div>
            
        <?php elseif ($page === 'announcements'): ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-bullhorn"></i>
                    公告与话题
                </h1>
            </div>
            
            <!-- 添加公告 -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle text-green-500"></i>
                    添加公告
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">公告内容</label>
                        <textarea name="content" class="form-textarea" placeholder="请输入公告内容（支持Markdown格式）"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">公告图片（可选）</label>
                        <input type="file" name="image" accept="image/*" class="form-input">
                    </div>
                    <button type="submit" name="add_announcement" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i>添加公告
                    </button>
                </form>
            </div>
            
            <!-- 公告列表 -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list text-blue-500"></i>
                    公告列表
                </div>
                <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-item">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div style="flex: 1;">
                            <div style="color: #718096; font-size: 14px; margin-bottom: 8px;">
                                <i class="fas fa-clock mr-2"></i><?php echo $announcement['created_at']; ?>
                            </div>
                            <div style="color: #2d3748; line-height: 1.6;"><?php echo $parsedown->text($announcement['content']); ?></div>
                            <?php if ($announcement['image']): ?>
                            <div style="margin-top: 12px;">
                                <img src="uploads/<?php echo $announcement['image']; ?>" style="max-width: 200px; border-radius: 8px;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 8px; margin-left: 16px;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="toggle_announcement" value="<?php echo $announcement['id']; ?>">
                                <button type="submit" class="btn <?php echo $announcement['active'] ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 8px 16px;">
                                    <?php echo $announcement['active'] ? '已启用' : '已禁用'; ?>
                                </button>
                            </form>
                            <button onclick="editAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['content']); ?>')" class="btn btn-secondary" style="padding: 8px 16px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这个公告吗？')">
                                <input type="hidden" name="delete_announcement" value="<?php echo $announcement['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 8px 16px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 话题管理 -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tags text-purple-500"></i>
                    话题管理
                </div>
                <form method="POST" style="margin-bottom: 24px;">
                    <div style="display: flex; gap: 12px;">
                        <input type="text" name="topic_name" placeholder="输入话题名称" class="form-input" style="flex: 1;">
                        <button type="submit" name="add_topic" class="btn btn-primary">
                            <i class="fas fa-plus mr-2"></i>添加话题
                        </button>
                    </div>
                </form>
                <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                    <?php foreach ($topics as $topic): ?>
                    <div style="display: flex; align-items: center; gap: 8px; background: rgba(102, 187, 106, 0.1); padding: 8px 16px; border-radius: 20px;">
                        <i class="fas fa-tag text-green-500"></i>
                        <span><?php echo htmlspecialchars($topic['name']); ?></span>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="delete_topic" value="<?php echo $topic['id']; ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- 编辑帖子弹窗 -->
    <div class="modal" id="editPostModal">
        <div class="modal-content">
            <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 24px;">编辑帖子</h3>
            <form method="POST">
                <input type="hidden" name="edit_post" id="editPostId">
                <div class="form-group">
                    <label class="form-label">帖子内容</label>
                    <textarea name="content" id="editPostContent" class="form-textarea"></textarea>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="closeModal('editPostModal')" class="btn btn-secondary">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 编辑公告弹窗 -->
    <div class="modal" id="editAnnouncementModal">
        <div class="modal-content">
            <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 24px;">编辑公告</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_announcement" value="1">
                <input type="hidden" name="edit_announcement_id" id="editAnnouncementId">
                <div class="form-group">
                    <label class="form-label">公告内容</label>
                    <textarea name="edit_announcement_content" id="editAnnouncementContent" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">公告图片（可选）</label>
                    <input type="file" name="edit_announcement_image" accept="image/*" class="form-input">
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="closeModal('editAnnouncementModal')" class="btn btn-secondary">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 捐赠弹窗 -->
    <div class="modal" id="donationModal">
        <div class="modal-content" style="text-align: center;">
            <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">感谢捐助</h3>
            <p style="color: #718096; margin-bottom: 24px;">您的捐助会让项目走得更远</p>
            <img src="https://clearlove.kazx.top/%E6%8D%90%E8%B5%A0.webp" style="max-width: 100%; border-radius: 12px; margin-bottom: 24px;">
            <button onclick="closeModal('donationModal')" class="btn btn-primary">关闭</button>
        </div>
    </div>
    
    <!-- 更新提示弹窗 -->
    <div class="modal" id="updateModal">
        <div class="modal-content">
            <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">发现新版本</h3>
            <div id="updateContent" style="margin-bottom: 24px;"></div>
            <div style="display: flex; gap: 12px;">
                <button onclick="closeModal('updateModal')" class="btn btn-secondary">稍后更新</button>
                <a id="updateLink" href="#" target="_blank" class="btn btn-primary">立即下载</a>
            </div>
        </div>
    </div>
    
    <!-- 云控公告弹窗 -->
    <?php if (is_array($cloudAnnouncements) && !isset($cloudAnnouncements['error']) && !empty($cloudAnnouncements)): ?>
    <?php foreach ($cloudAnnouncements as $announcement): ?>
    <?php if (!isset($_COOKIE['cloud_announcement_seen_' . $announcement['id']])): ?>
    <div id="cloudAnnouncementModal_<?php echo $announcement['id']; ?>" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4" style="animation: slideUp 0.3s ease;">
            <div class="flex justify-between items-center mb-4">
                <h3 style="font-size: 20px; font-weight: 600; color: #1a202c;"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                <button onclick="closeCloudAnnouncement(<?php echo $announcement['id']; ?>, true)" style="color: #718096; font-size: 24px; background: none; border: none; cursor: pointer;">&times;</button>
            </div>
            <div style="margin-bottom: 16px; color: #4a5568;">
                <?php echo $parsedown->text($announcement['content']); ?>
            </div>
            <div style="display: flex; gap: 16px;">
                <button onclick="closeCloudAnnouncement(<?php echo $announcement['id']; ?>, true)" style="flex: 1; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; color: #4a5568; background: white; cursor: pointer;">
                    今天不再显示
                </button>
                <button onclick="closeCloudAnnouncement(<?php echo $announcement['id']; ?>, false)" style="flex: 1; padding: 10px; background: linear-gradient(135deg, #66bb6a, #42a5f5); color: white; border: none; border-radius: 8px; cursor: pointer;">
                    我知道了
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- 公告弹窗 -->
    <div class="modal" id="announcementModal">
        <div class="modal-content">
            <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">公告</h3>
            <div id="announcementContent" style="margin-bottom: 24px;"></div>
            <button onclick="closeAnnouncementModal()" class="btn btn-primary">知道了</button>
        </div>
    </div>
    
    <script>
        // Toggle Switch函数
        function toggleSwitch(element) {
            element.classList.toggle('active');
            var checkbox = element.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
            }
        }
        
        // 页面加载动画控制
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('pageLoader').classList.remove('active');
        });
        
        // 导航链接点击时显示加载动画
        document.querySelectorAll('.nav-item').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('logout')) {
                    document.getElementById('pageLoader').classList.add('active');
                }
            });
        });
        
        // 主题选择
        function selectTheme(element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedTheme').value = element.dataset.theme;
            document.getElementById('customColors').value = element.dataset.colors;
        }
        
        // 展开/收起全部主题
        function toggleAllThemes() {
            const hiddenThemes = document.querySelectorAll('#regularThemesContainer .theme-option.hidden');
            const btn = document.getElementById('toggleThemesBtn');
            const isExpanded = hiddenThemes.length === 0;
            
            if (isExpanded) {
                let count = 0;
                document.querySelectorAll('#regularThemesContainer .theme-option').forEach(theme => {
                    count++;
                    if (count > 10) {
                        theme.classList.add('hidden');
                    }
                });
                btn.innerHTML = '<i class="fas fa-chevron-down mr-2"></i><span>展开全部</span>';
            } else {
                hiddenThemes.forEach(theme => {
                    theme.classList.remove('hidden');
                });
                btn.innerHTML = '<i class="fas fa-chevron-up mr-2"></i><span>收起</span>';
            }
        }
        
        // 展开/收起节日主题
        function toggleHolidayThemes() {
            const container = document.getElementById('holidayThemesContainer');
            const icon = document.getElementById('holidayThemeIcon');
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'grid';
                icon.style.transform = 'rotate(180deg)';
            } else {
                container.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            }
        }
        
        // 更新自定义颜色
        function updateCustomColors() {
            const color1 = document.getElementById('customColor1').value;
            const color2 = document.getElementById('customColor2').value;
            document.getElementById('customColors').value = color1 + ',' + color2;
        }
        
        // 移动端侧边栏切换
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.overlay');
            const menuIcon = document.getElementById('menuIcon');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            
            if (sidebar.classList.contains('open')) {
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            } else {
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        }
        
        // 编辑帖子
        function editPost(id, content) {
            document.getElementById('editPostId').value = id;
            document.getElementById('editPostContent').value = content;
            document.getElementById('editPostModal').classList.add('active');
        }
        
        // 编辑公告
        function editAnnouncement(id, content) {
            document.getElementById('editAnnouncementId').value = id;
            document.getElementById('editAnnouncementContent').value = content;
            document.getElementById('editAnnouncementModal').classList.add('active');
        }
        
        // 关闭弹窗
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // 显示捐赠弹窗
        function showDonation() {
            document.getElementById('donationModal').classList.add('active');
        }
        
        // 检查更新
        function checkUpdate() {
            fetch('api.php?action=check_update')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.has_update) {
                            document.getElementById('updateContent').innerHTML = 
                                '<p style="margin-bottom: 12px;"><strong>新版本：</strong>v' + data.version + '</p>' +
                                '<p style="margin-bottom: 12px;"><strong>更新说明：</strong></p>' +
                                '<p style="color: #718096;">' + data.release_notes + '</p>';
                            document.getElementById('updateLink').href = data.download_url;
                            document.getElementById('updateModal').classList.add('active');
                        } else {
                            alert('当前已是最新版本');
                        }
                    } else {
                        alert('检查更新失败: ' + (data.error || '未知错误'));
                    }
                })
                .catch(error => {
                    alert('检查更新失败: 网络错误');
                });
        }
        
        // 使用云端词库
        function useCloudSensitiveWords() {
            fetch('api.php?action=get_cloud_sensitive_words')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector('textarea[name="sensitive_words"]').value = data.words;
                        alert('云端词库已加载');
                    } else {
                        alert('获取云端词库失败: ' + (data.error || '未知错误'));
                    }
                })
                .catch(error => {
                    alert('获取云端词库失败: 网络错误');
                });
        }
        
        // 上传音乐
        function uploadMusic(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('bg_music_file', input.files[0]);
                
                const progressBar = document.getElementById('musicProgress');
                const progressFill = document.getElementById('musicProgressFill');
                progressBar.style.display = 'block';
                
                const xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressFill.style.width = percent + '%';
                    }
                });
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('音乐上传成功！请点击"保存配置"按钮以应用更改。');
                            location.reload();
                        } else {
                            alert('音乐上传失败: ' + (response.error || '未知错误'));
                        }
                    } else {
                        alert('音乐上传失败');
                    }
                    progressBar.style.display = 'none';
                    progressFill.style.width = '0%';
                });
                
                xhr.open('POST', 'api.php?action=upload_music');
                xhr.send(formData);
            }
        }
        
        // 音量滑块
        document.querySelector('input[name="bg_music_volume"]')?.addEventListener('input', function() {
            document.getElementById('volumeValue').textContent = this.value + '%';
        });
        
        // 关闭公告弹窗
        function closeAnnouncementModal() {
            document.getElementById('announcementModal').classList.remove('active');
            const today = new Date().toDateString();
            localStorage.setItem('announcementDismissed', today);
        }
        
        // 访问统计图表
        <?php if ($page === 'dashboard'): ?>
        const ctx = document.getElementById('visitChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['6天前', '5天前', '4天前', '3天前', '2天前', '昨天', '今天'],
                datasets: [{
                    label: '访问IP数',
                    data: <?php echo json_encode($visitStats['daily']); ?>,
                    borderColor: '#66bb6a',
                    backgroundColor: 'rgba(102, 187, 106, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#66bb6a',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // 文件类型占用图表
        const storageCtx = document.getElementById('storageChart').getContext('2d');
        
        <?php
        $labels = [];
        $data = [];
        $backgroundColors = [
            '#66bb6a', '#42a5f5', '#ec407a', '#7e57c2', '#ffa726',
            '#ef5350', '#26c6da', '#ffca28', '#3f51b5', '#5e35b1',
            '#29b6f6', '#cddc39', '#00bcd4', '#66bb6a', '#9c27b0',
            '#e53935', '#4caf50', '#ff9800', '#1e88e5', '#7b1fa2'
        ];
        $colors = [];
        
        arsort($fileTypes);
        
        $topTypes = array_slice($fileTypes, 0, 9);
        $otherSize = array_sum(array_slice($fileTypes, 9));
        
        if ($otherSize > 0) {
            $topTypes['其他'] = $otherSize;
        }
        
        foreach ($topTypes as $extension => $size) {
            $labels[] = $extension ?: '无扩展名';
            $data[] = $size;
            $colors[] = current($backgroundColors);
            next($backgroundColors);
        }
        
        $labelsJson = json_encode($labels);
        $dataJson = json_encode($data);
        $colorsJson = json_encode($colors);
        ?>
        
        new Chart(storageCtx, {
            type: 'pie',
            data: {
                labels: <?php echo $labelsJson; ?>,
                datasets: [{
                    data: <?php echo $dataJson; ?>,
                    backgroundColor: <?php echo $colorsJson; ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return label + ': ' + (value / 1024 / 1024).toFixed(2) + ' MB (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // 关闭云控公告
        function closeCloudAnnouncement(id, noShowToday) {
            const modal = document.getElementById('cloudAnnouncementModal_' + id);
            if (modal) {
                modal.remove();
            }
            if (noShowToday) {
                document.cookie = 'cloud_announcement_seen_' + id + '=1; expires=' + new Date(Date.now() + 86400000).toUTCString() + '; path=/';
            }
        }
        
        // 显示敏感词库更新提示
        <?php if (isset($_SESSION['words_updated'])): ?>
        setTimeout(function() {
            alert('敏感词库已自动更新');
            <?php unset($_SESSION['words_updated']); ?>
        }, 500);
        <?php endif; ?>
        
        // 显示版本更新提示
        <?php if (isset($updateInfo['has_update']) && $updateInfo['has_update']): ?>
        setTimeout(function() {
            document.getElementById('updateContent').innerHTML = 
                '<p style="margin-bottom: 12px;"><strong>新版本：</strong>v<?php echo $updateInfo['version']; ?></p>' +
                '<p style="margin-bottom: 12px;"><strong>更新说明：</strong></p>' +
                '<p style="color: #718096;"><?php echo addslashes($updateInfo['release_notes']); ?></p>';
            document.getElementById('updateLink').href = '<?php echo $updateInfo['download_url']; ?>';
            document.getElementById('updateModal').classList.add('active');
        }, 1000);
        <?php endif; ?>
        
        // 测试云端AI审核
        function testCloudAi() {
            const content = document.getElementById('cloudAiTestContent').value.trim();
            const resultDiv = document.getElementById('cloudAiTestResult');
            
            if (!content) {
                alert('请输入测试内容');
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div style="padding: 16px; background: #eff6ff; border-radius: 8px; color: #1e40af;"><i class="fas fa-spinner fa-spin mr-2"></i>正在调用云端AI审核（通过本地API代理）...</div>';
            
            // 通过本地API代理调用云控中心，避免浏览器混合内容拦截
            const apiUrl = 'api.php?action=cloud_ai_test';
            
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'content=' + encodeURIComponent(content)
            })
            .then(async res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.json();
            })
            .then(data => {
                // 显示调试信息
                let debugHtml = '';
                if (data._debug) {
                    debugHtml = `
                        <div style="margin-top: 12px; padding: 10px; background: #f3f4f6; border-radius: 6px; font-size: 12px; font-family: monospace;">
                            <strong>诊断信息：</strong><br>
                            AI审核开关：${data._debug.ai_enabled ? '已开启' : '未开启'}<br>
                            API状态：${data._debug.cloud_response}<br>
                            ${data._debug.cloud_error ? '错误信息：' + data._debug.cloud_error : ''}
                        </div>
                    `;
                }
                
                if (data.success) {
                    if (data.safe) {
                        resultDiv.innerHTML = `
                            <div style="padding: 16px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px;">
                                <div style="font-weight: 600; color: #155724; margin-bottom: 8px;">
                                    <i class="fas fa-check-circle mr-2"></i>✅ 内容安全，允许发布
                                </div>
                                ${data.reason ? '<div style="font-size: 14px; color: #666;">原因：' + data.reason + '</div>' : ''}
                                ${debugHtml}
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <div style="padding: 16px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px;">
                                <div style="font-weight: 600; color: #721c24; margin-bottom: 8px;">
                                    <i class="fas fa-times-circle mr-2"></i>❌ 内容违规，将被拦截
                                </div>
                                ${data.reason ? '<div style="font-size: 14px; color: #666;">原因：' + data.reason + '</div>' : ''}
                                ${debugHtml}
                            </div>
                        `;
                    }
                } else {
                    let errorContent = data.error || '未知错误';
                    let detailContent = data.detail ? '<div style="font-size: 12px; color: #999; margin-top: 8px;">详情：' + data.detail + '</div>' : '';
                    
                    resultDiv.innerHTML = `
                        <div style="padding: 16px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
                            <div style="font-weight: 600; color: #856404; margin-bottom: 8px;">
                                <i class="fas fa-exclamation-triangle mr-2"></i>❌ 测试失败
                            </div>
                            <div style="font-size: 14px; color: #666;">${errorContent}</div>
                            ${detailContent}
                            ${debugHtml}
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('[CloudAI Test Error]', err);
                
                let errorMsg = '请求失败';
                let errorDetail = err.message;
                let suggestion = '';
                
                if (err.message.includes('NetworkError') || err.message.includes('Failed to fetch')) {
                    errorMsg = '🌐 网络连接失败';
                    errorDetail = '无法访问本地API接口（api.php）';
                    suggestion = `
                        <div style="margin-top: 12px; padding: 12px; background: #fef2f2; border-radius: 6px; font-size: 13px;">
                            <strong>可能原因：</strong><br>
                            api.php 文件不存在或路径不正确
                        </div>
                    `;
                }
                
                resultDiv.innerHTML = `
                    <div style="padding: 20px; background: #fef2f2; border: 2px solid #ef4444; border-radius: 8px; color: #991b1b;">
                        <div style="font-weight: 600; margin-bottom: 12px; font-size: 16px;">
                            <i class="fas fa-times-circle mr-2"></i>${errorMsg}
                        </div>
                        <div style="font-size: 14px; margin-bottom: 8px;">
                            <strong>技术细节：</strong>${errorDetail}
                        </div>
                        ${suggestion}
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
