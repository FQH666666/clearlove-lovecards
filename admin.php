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
                    // 更新配置文件
                    $envContent = "<?php\n";
                    $envContent .= "define('DB_TYPE', '" . addslashes(DB_TYPE) . "');\n";
                    if (DB_TYPE === 'mysql') {
                        $envContent .= "define('DB_HOST', '" . addslashes(DB_HOST) . "');\n";
                        $envContent .= "define('DB_NAME', '" . addslashes(DB_NAME) . "');\n";
                        $envContent .= "define('DB_USER', '" . addslashes(DB_USER) . "');\n";
                        $envContent .= "define('DB_PASS', '" . addslashes(DB_PASS) . "');\n";
                    }
                    $envContent .= "define('SITE_NAME', '" . addslashes(SITE_NAME) . "');\n";
                    $envContent .= "define('THEME', '" . addslashes(defined('THEME') ? THEME : 'default') . "');\n";
                    $envContent .= "define('CUSTOM_COLORS', '" . addslashes(defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5') . "');\n";
                    $envContent .= "define('PURE_MODE', true);\n";
                    $envContent .= "define('SENSITIVE_WORDS', '" . addslashes($cloudWords) . "');\n";
                    $envContent .= "define('THEME_AUTO_SWITCH', '" . addslashes(defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') . "');\n";
                    $envContent .= "define('INSTALLED', true);\n";
                    file_put_contents(__DIR__ . '/.env.php', $envContent);
                    
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
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-xl p-8 max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent mb-2">
                <i class="fas fa-cog mr-2"></i>后台管理
            </h1>
            <p class="text-gray-500">请登录以继续</p>
        </div>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">管理员账号</label>
                <input type="text" name="username" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 mb-2">管理员密码</label>
                <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 rounded-xl font-semibold hover:opacity-90 transition-opacity">
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
    header('Location: admin.php');
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
        $db->exec("INSERT IGNORE INTO topics (name) VALUES ($name)");
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
    if (isset($_POST['update_site_name'])) {
        $envContent = "<?php\n";
        $envContent .= "define('DB_TYPE', '" . addslashes(DB_TYPE) . "');\n";
        if (DB_TYPE === 'mysql') {
            $envContent .= "define('DB_HOST', '" . addslashes(DB_HOST) . "');\n";
            $envContent .= "define('DB_NAME', '" . addslashes(DB_NAME) . "');\n";
            $envContent .= "define('DB_USER', '" . addslashes(DB_USER) . "');\n";
            $envContent .= "define('DB_PASS', '" . addslashes(DB_PASS) . "');\n";
        }
        $envContent .= "define('SITE_NAME', '" . addslashes($_POST['site_name']) . "');\n";
        $envContent .= "define('THEME', '" . addslashes(defined('THEME') ? THEME : 'default') . "');\n";
        $envContent .= "define('CUSTOM_COLORS', '" . addslashes(defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5') . "');\n";
        $envContent .= "define('PURE_MODE', " . (defined('PURE_MODE') ? PURE_MODE : 'false') . ");\n";
        $envContent .= "define('SENSITIVE_WORDS', '" . addslashes(defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '') . "');\n";
        $envContent .= "define('THEME_AUTO_SWITCH', '" . addslashes(defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') . "');\n";
        $envContent .= "define('INSTALLED', true);\n";
        file_put_contents(__DIR__ . '/.env.php', $envContent);
    }
    
    if (isset($_POST['update_theme'])) {
        $envContent = "<?php\n";
        $envContent .= "define('DB_TYPE', '" . addslashes(DB_TYPE) . "');\n";
        if (DB_TYPE === 'mysql') {
            $envContent .= "define('DB_HOST', '" . addslashes(DB_HOST) . "');\n";
            $envContent .= "define('DB_NAME', '" . addslashes(DB_NAME) . "');\n";
            $envContent .= "define('DB_USER', '" . addslashes(DB_USER) . "');\n";
            $envContent .= "define('DB_PASS', '" . addslashes(DB_PASS) . "');\n";
        }
        $envContent .= "define('SITE_NAME', '" . addslashes(SITE_NAME) . "');\n";
        $envContent .= "define('THEME', '" . addslashes($_POST['theme']) . "');\n";
        $envContent .= "define('CUSTOM_COLORS', '" . addslashes($_POST['custom_colors']) . "');\n";
        $envContent .= "define('PURE_MODE', " . (defined('PURE_MODE') ? PURE_MODE : 'false') . ");\n";
        $envContent .= "define('SENSITIVE_WORDS', '" . addslashes(defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : '') . "');\n";
        $envContent .= "define('THEME_AUTO_SWITCH', '" . addslashes($_POST['theme_auto_switch']) . "');\n";
        $envContent .= "define('INSTALLED', true);\n";
        file_put_contents(__DIR__ . '/.env.php', $envContent);
    }
    
    if (isset($_POST['update_pure_mode'])) {
        $envContent = "<?php\n";
        $envContent .= "define('DB_TYPE', '" . addslashes(DB_TYPE) . "');\n";
        if (DB_TYPE === 'mysql') {
            $envContent .= "define('DB_HOST', '" . addslashes(DB_HOST) . "');\n";
            $envContent .= "define('DB_NAME', '" . addslashes(DB_NAME) . "');\n";
            $envContent .= "define('DB_USER', '" . addslashes(DB_USER) . "');\n";
            $envContent .= "define('DB_PASS', '" . addslashes(DB_PASS) . "');\n";
        }
        $envContent .= "define('SITE_NAME', '" . addslashes(SITE_NAME) . "');\n";
        $envContent .= "define('THEME', '" . addslashes(defined('THEME') ? THEME : 'default') . "');\n";
        $envContent .= "define('CUSTOM_COLORS', '" . addslashes(defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5') . "');\n";
        $envContent .= "define('PURE_MODE', " . (isset($_POST['pure_mode']) ? 'true' : 'false') . ");\n";
        $envContent .= "define('SENSITIVE_WORDS', '" . addslashes($_POST['sensitive_words']) . "');\n";
        $envContent .= "define('THEME_AUTO_SWITCH', '" . addslashes(defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') . "');\n";
        $envContent .= "define('INSTALLED', true);\n";
        file_put_contents(__DIR__ . '/.env.php', $envContent);
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
        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #e3f2fd 100%);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(102,187,106,0.2) 0%, rgba(66,165,245,0.2) 100%);
            border-right: 3px solid #66bb6a;
        }
        
        /* 移动端适配 */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                height: 100vh;
                width: 85%;
                max-width: 280px;
                z-index: 100;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }
            .sidebar.open {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }
            .mobile-menu-btn {
                display: block !important;
                position: fixed;
                top: 16px;
                left: 16px;
                z-index: 101;
                width: 48px;
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }
            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 99;
                display: none;
                transition: opacity 0.3s ease;
            }
            .overlay.active {
                display: block;
                opacity: 1;
            }
            .grid-cols-4 {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .grid-cols-2 {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            h2 {
                font-size: 1.5rem;
                margin-bottom: 16px;
            }
            .bg-white\/90 {
                border-radius: 12px;
                padding: 16px;
                margin-bottom: 16px;
            }
            button {
                font-size: 14px;
                padding: 10px 16px;
            }
            input, textarea, select {
                font-size: 14px;
                padding: 12px;
            }
            .theme-option {
                height: 80px;
            }
            .theme-option .h-16 {
                height: 48px;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- 移动端菜单按钮 -->
    <button class="mobile-menu-btn fixed top-4 left-4 z-101 bg-white/90 backdrop-blur-lg p-2 rounded-lg shadow-lg text-gray-700 hidden" onclick="toggleSidebar()">
        <i class="fas fa-bars text-xl transition-transform duration-300" id="menuIcon"></i>
    </button>
    
    <!-- 侧边栏覆盖层 -->
    <div class="overlay" onclick="toggleSidebar()"></div>
    
    <div class="flex min-h-screen">
        <aside class="sidebar w-64 bg-white/90 backdrop-blur-lg shadow-xl">
            <div class="p-6 border-b border-gray-100">
                <h1 class="text-xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent">
                    <i class="fas fa-cog mr-2"></i>后台管理
                </h1>
            </div>
            <nav class="p-4">
                <a href="?page=dashboard" class="sidebar-link flex items-center px-4 py-3 rounded-lg mb-2 text-gray-700 hover:bg-gray-50 <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home w-5 mr-3"></i>首页
                </a>
                <a href="?page=config" class="sidebar-link flex items-center px-4 py-3 rounded-lg mb-2 text-gray-700 hover:bg-gray-50 <?php echo $page === 'config' ? 'active' : ''; ?>">
                    <i class="fas fa-sliders-h w-5 mr-3"></i>网站配置
                </a>
                <a href="?page=posts" class="sidebar-link flex items-center px-4 py-3 rounded-lg mb-2 text-gray-700 hover:bg-gray-50 <?php echo $page === 'posts' ? 'active' : ''; ?>">
                    <i class="fas fa-list w-5 mr-3"></i>帖子管理
                </a>
                <a href="?page=announcements" class="sidebar-link flex items-center px-4 py-3 rounded-lg mb-2 text-gray-700 hover:bg-gray-50 <?php echo $page === 'announcements' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn w-5 mr-3"></i>公告与话题
                </a>
                <a href="?page=logout" class="flex items-center px-4 py-3 rounded-lg text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt w-5 mr-3"></i>退出登录
                </a>
            </nav>
        </aside>

        <main class="main-content flex-1 p-4 md:p-8">
            <?php if ($page === 'dashboard'): ?>
                <h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-home mr-2 text-green-600"></i>数据概览</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">帖子总数</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $totalPosts; ?></p>
                            </div>
                            <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-heart text-green-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">今日访问IP</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $visitStats['today']; ?></p>
                            </div>
                            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">近7日访问IP</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $visitStats['week']; ?></p>
                            </div>
                            <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">网站总占用空间</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo formatFileSize($totalStorage); ?></p>
                            </div>
                            <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-hdd text-yellow-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-chart-bar mr-2 text-blue-500"></i>近7日访问统计</h3>
                        <canvas id="visitChart" height="300"></canvas>
                    </div>
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-chart-pie mr-2 text-yellow-500"></i>文件类型占用比例</h3>
                        <canvas id="storageChart" height="300"></canvas>
                    </div>
                </div>

                <script>
                    // 访问统计图表
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
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } }
                        }
                    });

                    // 文件类型占用比例图表
                    const storageCtx = document.getElementById('storageChart').getContext('2d');
                    
                    // 准备数据
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
                    
                    // 排序文件类型，按大小降序
                    arsort($fileTypes);
                    
                    // 取前10种文件类型，其余归为"其他"
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
                                            return `${label}: ${(value / 1024 / 1024).toFixed(2)} MB (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                </script>
                
                <script>
                    // 移动端侧边栏切换
                    function toggleSidebar() {
                        const sidebar = document.querySelector('.sidebar');
                        const overlay = document.querySelector('.overlay');
                        const menuIcon = document.getElementById('menuIcon');
                        
                        sidebar.classList.toggle('open');
                        overlay.classList.toggle('active');
                        
                        // 菜单图标动画
                        if (sidebar.classList.contains('open')) {
                            menuIcon.style.transform = 'rotate(90deg)';
                        } else {
                            menuIcon.style.transform = 'rotate(0)';
                        }
                    }
                </script>
            <?php elseif ($page === 'config'): ?>
                <h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-sliders-h mr-2 text-blue-500"></i>网站配置</h2>
                
                <!-- 网站名称配置 -->
                <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg max-w-2xl mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-globe mr-2 text-blue-500"></i>网站名称</h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">网站名称</label>
                            <input type="text" name="site_name" value="<?php echo SITE_NAME; ?>" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                        <input type="hidden" name="update_site_name" value="1">
                        <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-3 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                            <i class="fas fa-save mr-2"></i>保存配置
                        </button>
                    </form>
                </div>
                
                <!-- 配色主题配置 -->
                <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg max-w-2xl mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-palette mr-2 text-purple-500"></i>配色主题</h3>
                    <form method="POST">
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2">选择主题</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
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
                                
                                <!-- 常规主题 -->
                                <div id="regularThemesContainer">
                                <?php
                                    $themeCount = 0;
                                    $totalThemes = count($regularThemes) - 1; // 减去自定义主题
                                    foreach ($regularThemes as $key => $theme) {
                                        if ($key === 'custom') continue;
                                        $themeCount++;
                                        $isActive = $currentTheme === $key;
                                        $style = 'background: linear-gradient(135deg, ' . $theme['colors'] . ');';
                                        $isHidden = $themeCount > 10;
                                    ?>
                                    <div class="theme-option relative cursor-pointer border-2 rounded-xl overflow-hidden <?php echo $isActive ? 'border-green-500' : 'border-gray-200'; ?> <?php echo $isHidden ? 'hidden' : ''; ?>" data-theme-key="<?php echo $key; ?>" onclick="document.getElementById('theme_<?php echo $key; ?>').checked = true; document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('border-green-500')); this.classList.add('border-green-500');">
                                        <input type="radio" id="theme_<?php echo $key; ?>" name="theme" value="<?php echo $key; ?>" <?php echo $isActive ? 'checked' : ''; ?> class="hidden">
                                        <div class="h-16" style="<?php echo $style; ?>"></div>
                                        <div class="p-2 text-center text-sm font-medium"><?php echo $theme['name']; ?></div>
                                    </div>
                                    <?php }
                                    ?>
                                </div>
                                
                                <!-- 自定义主题 -->
                                <div class="theme-option relative cursor-pointer border-2 rounded-xl overflow-hidden <?php echo $currentTheme === 'custom' ? 'border-green-500' : 'border-gray-200'; ?>" onclick="document.getElementById('theme_custom').checked = true; document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('border-green-500')); this.classList.add('border-green-500');">
                                    <input type="radio" id="theme_custom" name="theme" value="custom" <?php echo $currentTheme === 'custom' ? 'checked' : ''; ?> class="hidden">
                                    <div class="h-16"></div>
                                    <div class="p-2 text-center text-sm font-medium">自定义主题</div>
                                </div>
                                
                                <!-- 展开/收起按钮 -->
                                <?php if ($totalThemes > 10): ?>
                                <div class="col-span-full mt-2">
                                    <button type="button" id="toggleThemesBtn" class="w-full py-2 text-sm text-blue-600 hover:text-blue-700 transition flex items-center justify-center gap-2">
                                        <i class="fas fa-chevron-down"></i>
                                        <span>展开全部</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <!-- 节日主题收纳条 -->
                                <div class="col-span-full mt-4">
                                    <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                                        <div class="bg-gray-50 p-3 cursor-pointer flex justify-between items-center" onclick="toggleHolidayThemes()">
                                            <h4 class="font-medium text-gray-700">节日主题</h4>
                                            <i id="holidayThemeIcon" class="fas fa-chevron-down text-gray-500 transition-transform"></i>
                                        </div>
                                        <div id="holidayThemesContainer" class="hidden p-3 grid grid-cols-2 md:grid-cols-3 gap-3">
                                            <?php foreach ($holidayThemes as $key => $theme) {
                                                $isActive = $currentTheme === $key;
                                                $style = 'background: linear-gradient(135deg, ' . $theme['colors'] . ');';
                                            ?>
                                            <div class="theme-option relative cursor-pointer border-2 rounded-xl overflow-hidden <?php echo $isActive ? 'border-green-500' : 'border-gray-200'; ?>" onclick="document.getElementById('theme_<?php echo $key; ?>').checked = true; document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('border-green-500')); this.classList.add('border-green-500');">
                                                <input type="radio" id="theme_<?php echo $key; ?>" name="theme" value="<?php echo $key; ?>" <?php echo $isActive ? 'checked' : ''; ?> class="hidden">
                                                <div class="h-16" style="<?php echo $style; ?>"></div>
                                                <div class="p-2 text-center text-sm font-medium"><?php echo $theme['name']; ?></div>
                                                <div class="absolute top-1 right-1 bg-white/80 backdrop-blur-sm rounded-full px-2 py-1 text-xs text-gray-600">
                                                    节日
                                                </div>
                                                <button type="button" class="absolute bottom-1 right-1 bg-white/80 backdrop-blur-sm rounded-full p-1 text-xs text-blue-600 hover:bg-white hover:text-blue-700 transition" onclick="event.stopPropagation(); previewHolidayTheme('<?php echo $key; ?>', '<?php echo $theme['name']; ?>', '<?php echo $theme['colors']; ?>');">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <?php }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                            function toggleHolidayThemes() {
                                const container = document.getElementById('holidayThemesContainer');
                                const icon = document.getElementById('holidayThemeIcon');
                                container.classList.toggle('hidden');
                                icon.classList.toggle('rotate-180');
                            }
                            
                            // 展开/收起常规主题
                            document.getElementById('toggleThemesBtn')?.addEventListener('click', function() {
                                const hiddenThemes = document.querySelectorAll('#regularThemesContainer .theme-option.hidden');
                                const isExpanded = hiddenThemes.length === 0;
                                
                                if (isExpanded) {
                                    // 收起
                                    let count = 0;
                                    document.querySelectorAll('#regularThemesContainer .theme-option').forEach(theme => {
                                        count++;
                                        if (count > 10) {
                                            theme.classList.add('hidden');
                                        }
                                    });
                                    this.innerHTML = '<i class="fas fa-chevron-down"></i><span>展开全部</span>';
                                } else {
                                    // 展开
                                    hiddenThemes.forEach(theme => {
                                        theme.classList.remove('hidden');
                                    });
                                    this.innerHTML = '<i class="fas fa-chevron-up"></i><span>收起</span>';
                                }
                            });
                            
                            function previewHolidayTheme(themeKey, themeName, themeColors) {
                                // 创建预览模态框
                                const modal = document.createElement('div');
                                modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
                                modal.onclick = function(e) {
                                    if (e.target === modal) {
                                        modal.remove();
                                    }
                                };
                                
                                // 预览内容
                                const previewContent = document.createElement('div');
                                previewContent.className = 'bg-white rounded-2xl p-6 max-w-md w-full mx-4';
                                previewContent.onclick = function(e) {
                                    e.stopPropagation();
                                };
                                
                                // 主题效果预览
                                const themePreview = document.createElement('div');
                                themePreview.className = 'rounded-xl overflow-hidden mb-4';
                                themePreview.style.height = '200px';
                                themePreview.style.background = `linear-gradient(135deg, ${themeColors})`;
                                
                                // 添加节日效果
                                let holidayEffect = '';
                                switch (themeKey) {
                                    case 'spring':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 20% 20%, rgba(255, 179, 0, 0.4) 0%, transparent 15%),
                                                radial-gradient(circle at 80% 20%, rgba(255, 179, 0, 0.4) 0%, transparent 15%),
                                                radial-gradient(circle at 20% 80%, rgba(255, 179, 0, 0.4) 0%, transparent 15%),
                                                radial-gradient(circle at 80% 80%, rgba(255, 179, 0, 0.4) 0%, transparent 15%);
                                                animation: lanterns 4s ease-in-out infinite;
                                            "></div>
                                            <style>
                                                @keyframes lanterns {
                                                    0%, 100% { transform: translateY(0px); }
                                                    50% { transform: translateY(-10px); }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'christmas':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 30% 10%, rgba(255, 255, 255, 0.8) 0%, transparent 5%),
                                                radial-gradient(circle at 70% 15%, rgba(255, 255, 255, 0.6) 0%, transparent 4%),
                                                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.7) 0%, transparent 3%);
                                                animation: snow 10s linear infinite;
                                            "></div>
                                            <style>
                                                @keyframes snow {
                                                    0% { transform: translateY(-100%); }
                                                    100% { transform: translateY(100%); }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'valentine':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 25% 25%, rgba(233, 30, 99, 0.3) 0%, transparent 15%),
                                                radial-gradient(circle at 75% 25%, rgba(233, 30, 99, 0.3) 0%, transparent 15%),
                                                radial-gradient(circle at 25% 75%, rgba(233, 30, 99, 0.3) 0%, transparent 15%);
                                                animation: hearts 3s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes hearts {
                                                    0%, 100% { transform: scale(1); opacity: 0.8; }
                                                    50% { transform: scale(1.1); opacity: 1; }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'midautumn':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 10%; right: 10%; width: 80px; height: 80px; background: radial-gradient(circle, rgba(255, 255, 224, 0.9) 0%, rgba(255, 255, 153, 0.7) 70%, transparent 100%); border-radius: 50%; box-shadow: 0 0 50px rgba(255, 255, 153, 0.6); animation: moon 20s linear infinite;"></div>
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 30% 30%, rgba(255, 255, 153, 0.2) 0%, transparent 10%),
                                                radial-gradient(circle at 70% 30%, rgba(255, 255, 153, 0.2) 0%, transparent 10%);
                                                animation: twinkling 3s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes moon {
                                                    0% { transform: rotate(0deg); }
                                                    100% { transform: rotate(360deg); }
                                                }
                                                @keyframes twinkling {
                                                    0%, 100% { opacity: 0.3; }
                                                    50% { opacity: 0.8; }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'mayday':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 20% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 20%),
                                                radial-gradient(circle at 80% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 20%);
                                                animation: leaves 6s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes leaves {
                                                    0%, 100% { transform: rotate(0deg); }
                                                    50% { transform: rotate(5deg); }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'children':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 15% 15%, rgba(255, 152, 0, 0.4) 0%, transparent 12%),
                                                radial-gradient(circle at 85% 15%, rgba(255, 152, 0, 0.4) 0%, transparent 12%),
                                                radial-gradient(circle at 15% 85%, rgba(255, 152, 0, 0.4) 0%, transparent 12%);
                                                animation: balloons 5s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes balloons {
                                                    0%, 100% { transform: translateY(0px) rotate(0deg); }
                                                    25% { transform: translateY(-10px) rotate(5deg); }
                                                    50% { transform: translateY(-5px) rotate(0deg); }
                                                    75% { transform: translateY(-10px) rotate(-5deg); }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'national':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 25% 25%, rgba(229, 57, 53, 0.3) 0%, transparent 15%),
                                                radial-gradient(circle at 75% 25%, rgba(229, 57, 53, 0.3) 0%, transparent 15%),
                                                radial-gradient(circle at 25% 75%, rgba(229, 57, 53, 0.3) 0%, transparent 15%);
                                                animation: fireworks 2s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes fireworks {
                                                    0%, 100% { opacity: 0.3; }
                                                    50% { opacity: 0.8; }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'qingming':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 20% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 15%),
                                                radial-gradient(circle at 80% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 15%),
                                                radial-gradient(circle at 50% 70%, rgba(76, 175, 80, 0.4) 0%, transparent 15%);
                                                animation: leaves 6s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes leaves {
                                                    0%, 100% { transform: rotate(0deg); }
                                                    50% { transform: rotate(5deg); }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'dragon':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 25% 25%, rgba(229, 57, 53, 0.4) 0%, transparent 15%),
                                                radial-gradient(circle at 75% 25%, rgba(229, 57, 53, 0.4) 0%, transparent 15%),
                                                radial-gradient(circle at 25% 75%, rgba(229, 57, 53, 0.4) 0%, transparent 15%);
                                                animation: dragonDance 4s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes dragonDance {
                                                    0%, 100% { transform: translateY(0px) rotate(0deg); }
                                                    25% { transform: translateY(-10px) rotate(2deg); }
                                                    50% { transform: translateY(-5px) rotate(0deg); }
                                                    75% { transform: translateY(-10px) rotate(-2deg); }
                                                }
                                            </style>
                                        `;
                                        break;
                                    case 'qixi':
                                        holidayEffect = `
                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: 
                                                radial-gradient(circle at 20% 20%, rgba(233, 30, 99, 0.3) 0%, transparent 12%),
                                                radial-gradient(circle at 80% 20%, rgba(233, 30, 99, 0.3) 0%, transparent 12%),
                                                radial-gradient(circle at 20% 80%, rgba(233, 30, 99, 0.3) 0%, transparent 12%);
                                                animation: stars 3s ease-in-out infinite;
                                            </div>
                                            <style>
                                                @keyframes stars {
                                                    0%, 100% { transform: scale(1); opacity: 0.7; }
                                                    50% { transform: scale(1.1); opacity: 1; }
                                                }
                                            </style>
                                        `;
                                        break;
                                }
                                
                                themePreview.style.position = 'relative';
                                themePreview.innerHTML = holidayEffect;
                                
                                // 主题信息
                                const themeInfo = document.createElement('div');
                                themeInfo.className = 'space-y-2';
                                themeInfo.innerHTML = `
                                    <h3 class="text-lg font-semibold text-gray-800">${themeName}预览</h3>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-600">主题颜色:</span>
                                        <div class="flex gap-2">
                                            ${themeColors.split(',').map(color => `<div class="w-6 h-6 rounded-full" style="background-color: ${color}"></div>`).join('')}
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600">点击空白处关闭预览</p>
                                `;
                                
                                // 关闭按钮
                                const closeButton = document.createElement('button');
                                closeButton.className = 'absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl';
                                closeButton.innerHTML = '&times;';
                                closeButton.onclick = function() {
                                    modal.remove();
                                };
                                
                                previewContent.appendChild(closeButton);
                                previewContent.appendChild(themePreview);
                                previewContent.appendChild(themeInfo);
                                modal.appendChild(previewContent);
                                document.body.appendChild(modal);
                            }
                        </script>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2">自定义颜色</label>
                            <div class="flex gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 mb-1">主色</label>
                                    <input type="color" name="custom_colors" value="<?php echo defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a'; ?>" class="w-full h-10 rounded-lg cursor-pointer">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 mb-1">辅色</label>
                                    <input type="color" name="custom_colors2" value="<?php echo defined('CUSTOM_COLORS') ? (explode(',', CUSTOM_COLORS)[1] ?? '#42a5f5') : '#42a5f5'; ?>" class="w-full h-10 rounded-lg cursor-pointer">
                                </div>
                            </div>
                            <input type="hidden" name="custom_colors" id="custom_colors_input" value="<?php echo defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5'; ?>">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2">自动切换设置</label>
                            <select name="theme_auto_switch" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                <option value="off" <?php echo (defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') === 'off' ? 'selected' : ''; ?>>不切换</option>
                                <option value="daily" <?php echo (defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') === 'daily' ? 'selected' : ''; ?>>每天切换</option>
                                <option value="random" <?php echo (defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off') === 'random' ? 'selected' : ''; ?>>随机切换</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-2">每天切换：如果现用的是节日主题则不生效<br>随机切换：每个用户每次打开随机背景主题（不包括节日主题）</p>
                        </div>
                        
                        <input type="hidden" name="update_theme" value="1">
                        <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-3 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                            <i class="fas fa-save mr-2"></i>保存配置
                        </button>
                    </form>
                </div>
                
                <!-- 纯净模式配置 -->
                <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg max-w-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-shield-alt mr-2 text-green-500"></i>纯净模式</h3>
                    <form method="POST">
                        <div class="mb-6">
                            <label class="flex items-center justify-between">
                                <span class="text-gray-700">开启纯净模式</span>
                                <div class="relative inline-block w-12 h-6 transition duration-200 ease-in-out">
                                    <input type="checkbox" name="pure_mode" <?php echo defined('PURE_MODE') && PURE_MODE ? 'checked' : ''; ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-200 ease-in-out" id="pure_mode_toggle">
                                    <label for="pure_mode_toggle" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer transition-colors duration-200 ease-in-out"></label>
                                </div>
                            </label>
                            <p class="text-sm text-gray-500 mt-2">开启后将拦截包含敏感词的帖子</p>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2">敏感词</label>
                            <textarea name="sensitive_words" id="sensitive_words" placeholder="请输入敏感词，用空格分隔" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition" rows="3"><?php echo defined('SENSITIVE_WORDS') ? SENSITIVE_WORDS : ''; ?></textarea>
                            <div class="flex gap-2 mt-2">
                                <button type="button" onclick="useCloudSensitiveWords()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition text-sm">
                                    <i class="fas fa-cloud-download-alt mr-1"></i>使用云端词库
                                </button>
                                <p class="text-sm text-gray-500">多个敏感词用空格分隔</p>
                            </div>
                        </div>
                        
                        <input type="hidden" name="update_pure_mode" value="1">
                        <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-3 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                            <i class="fas fa-save mr-2"></i>保存配置
                        </button>
                    </form>
                </div>
                
                <style>
                    .toggle-checkbox:checked {
                        right: 0;
                        border-color: #66bb6a;
                    }
                    .toggle-checkbox:checked + .toggle-label {
                        background-color: #66bb6a;
                    }
                    .toggle-checkbox {
                        right: 6px;
                        top: 6px;
                    }
                </style>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const colorInputs = document.querySelectorAll('input[type="color"]');
                        const customColorsInput = document.getElementById('custom_colors_input');
                        
                        function updateCustomColors() {
                            const color1 = document.querySelector('input[name="custom_colors"]').value;
                            const color2 = document.querySelector('input[name="custom_colors2"]').value;
                            customColorsInput.value = color1 + ',' + color2;
                        }
                        
                        colorInputs.forEach(input => {
                            input.addEventListener('change', updateCustomColors);
                        });
                    });
                    
                    function useCloudSensitiveWords() {
                        fetch('api.php?action=get_cloud_sensitive_words')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('sensitive_words').value = data.words;
                                    alert('已成功获取云端词库');
                                } else {
                                    alert('获取云端词库失败：' + (data.error || '未知错误'));
                                }
                            })
                            .catch(error => {
                                alert('获取云端词库失败：网络错误');
                            });
                    }
                </script>
            <?php elseif ($page === 'posts'): ?>
                <h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-list mr-2 text-purple-500"></i>帖子管理</h2>
                <div class="space-y-4">
                    <?php foreach ($posts as $post): ?>
                        <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($post['nickname']); ?> · <?php echo formatTime($post['created_at']); ?></div>
                                    <p class="text-gray-600 mb-2"><?php echo htmlspecialchars(mb_substr($post['content'], 0, 100)); ?><?php echo mb_strlen($post['content']) > 100 ? '...' : ''; ?></p>
                                </div>
                                <div class="flex gap-2 ml-4">
                                    <button onclick="toggleEdit(<?php echo $post['id']; ?>)" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200 transition">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('确定删除吗？');">
                                        <input type="hidden" name="delete_post" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200 transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div id="editForm<?php echo $post['id']; ?>" class="hidden mb-4">
                                <form method="POST">
                                    <textarea name="content" class="w-full px-4 py-3 border border-gray-200 rounded-xl mb-2" rows="3"><?php echo htmlspecialchars($post['content']); ?></textarea>
                                    <input type="hidden" name="edit_post" value="<?php echo $post['id']; ?>">
                                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm">保存</button>
                                </form>
                            </div>
                            <?php 
                            $comments = getComments($db, $post['id']);
                            if ($comments): 
                            ?>
                                <div class="border-t border-gray-100 pt-4 mt-4">
                                    <h4 class="font-medium text-gray-700 mb-3">评论 (<?php echo count($comments); ?>)</h4>
                                    <div class="space-y-2">
                                        <?php foreach ($comments as $comment): ?>
                                            <div class="flex items-start justify-between bg-gray-50 p-3 rounded-lg">
                                                <div class="flex-1">
                                                    <span class="font-medium text-gray-700"><?php echo htmlspecialchars($comment['nickname']); ?></span>
                                                    <span class="text-gray-400 text-sm ml-2"><?php echo formatTime($comment['created_at']); ?></span>
                                                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($comment['content']); ?></p>
                                                </div>
                                                <form method="POST" class="ml-3" onsubmit="return confirm('确定删除吗？');">
                                                    <input type="hidden" name="delete_comment" value="<?php echo $comment['id']; ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <script>
                    function toggleEdit(id) {
                        document.getElementById('editForm' + id).classList.toggle('hidden');
                    }
                </script>
            <?php elseif ($page === 'announcements'): ?>
                <h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-bullhorn mr-2 text-yellow-500"></i>公告与话题</h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-bullhorn mr-2 text-yellow-500"></i>公告管理</h3>
                        <form method="POST" enctype="multipart/form-data" class="mb-6">
                            <textarea name="content" placeholder="公告内容" rows="3" required class="w-full px-4 py-3 border border-gray-200 rounded-xl mb-3 focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition"></textarea>
                            <input type="file" name="image" accept="image/*" class="mb-3">
                            <button type="submit" name="add_announcement" class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white px-6 py-2 rounded-xl font-medium hover:opacity-90 transition-opacity">
                                添加公告
                            </button>
                        </form>
                        <div class="space-y-3">
                            <?php foreach ($announcements as $a): ?>
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="<?php echo $a['active'] ? 'text-green-600' : 'text-gray-400'; ?>">
                                            <i class="fas <?php echo $a['active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <?php echo $a['active'] ? '启用中' : '已禁用'; ?>
                                        </span>
                                        <div class="flex gap-2">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="toggle_announcement" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="text-blue-500 hover:text-blue-700 text-sm">
                                                    <?php echo $a['active'] ? '禁用' : '启用'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('确定删除吗？');">
                                                <input type="hidden" name="delete_announcement" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-sm">删除</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="text-gray-700 prose max-w-none"><?php echo $parsedown->text($a['content']); ?></div>
                                    <?php if ($a['image']): ?>
                                        <img src="uploads/<?php echo $a['image']; ?>" class="mt-2 w-32 h-32 object-cover rounded-lg">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl p-6 shadow-lg">
                        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-hashtag mr-2 text-purple-500"></i>话题管理</h3>
                        <form method="POST" class="mb-6">
                            <div class="flex gap-2">
                                <input type="text" name="topic_name" placeholder="话题名称" required class="flex-1 px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                <button type="submit" name="add_topic" class="bg-purple-500 text-white px-4 py-2 rounded-xl hover:bg-purple-600 transition">
                                    添加
                                </button>
                            </div>
                        </form>
                        <div class="space-y-2">
                            <?php foreach ($topics as $t): ?>
                                <div class="flex items-center justify-between bg-gray-50 p-3 rounded-xl">
                                    <span class="text-gray-700">#<?php echo htmlspecialchars($t['name']); ?></span>
                                    <form method="POST" class="inline" onsubmit="return confirm('确定删除吗？');">
                                        <input type="hidden" name="delete_topic" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- 更新提示弹窗 -->
    <?php if (isset($updateInfo['has_update']) && $updateInfo['has_update']): ?>
    <div id="updateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="text-center mb-4">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-arrow-up text-2xl text-blue-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">发现新版本</h3>
                <p class="text-gray-600 mt-2">版本：<?php echo $updateInfo['version']; ?></p>
            </div>
            <div class="mb-4">
                <h4 class="font-medium text-gray-700 mb-2">更新说明：</h4>
                <div class="bg-gray-50 p-3 rounded-lg text-gray-600 text-sm">
                    <?php echo nl2br(htmlspecialchars($updateInfo['release_notes'])); ?>
                </div>
            </div>
            <div class="flex gap-4">
                <button onclick="document.getElementById('updateModal').remove()" class="flex-1 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    暂不更新
                </button>
                <a href="<?php echo $updateInfo['download_url']; ?>" target="_blank" class="flex-1 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:opacity-90 transition">
                    立即下载
                </a>
            </div>
        </div>
    </div>
    <?php elseif (isset($updateInfo['error'])): ?>
    <div id="updateErrorModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="text-center mb-4">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">加载版本失败</h3>
                <p class="text-gray-600 mt-2">错误：<?php echo $updateInfo['error']; ?></p>
            </div>
            <button onclick="document.getElementById('updateErrorModal').remove()" class="w-full py-2 bg-gradient-to-r from-gray-500 to-gray-700 text-white rounded-lg hover:opacity-90 transition">
                确定
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- 敏感词库更新提示 -->
    <?php if (isset($_SESSION['words_updated']) && $_SESSION['words_updated']): ?>
    <?php unset($_SESSION['words_updated']); ?>
    <div id="wordsUpdateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full mx-4 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-circle text-2xl text-green-600"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">敏感词库已自动更新</h3>
        </div>
    </div>
    <script>
        setTimeout(function() {
            const modal = document.getElementById('wordsUpdateModal');
            if (modal) {
                modal.remove();
            }
        }, 2000);
    </script>
    <?php endif; ?>

    <!-- 云控公告弹窗 -->
    <?php if (is_array($cloudAnnouncements) && !isset($cloudAnnouncements['error']) && !empty($cloudAnnouncements)): ?>
    <?php foreach ($cloudAnnouncements as $announcement): ?>
    <?php if (!isset($_COOKIE['cloud_announcement_seen_' . $announcement['id']])): ?>
    <div id="cloudAnnouncementModal_<?php echo $announcement['id']; ?>" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                <button onclick="closeCloudAnnouncement(<?php echo $announcement['id']; ?>, true)" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <div class="mb-4 prose max-w-none text-gray-700">
                <?php echo $parsedown->text($announcement['content']); ?>
            </div>
            <div class="flex gap-4">
                <button onclick="closeCloudAnnouncement(<?php echo $announcement['id']; ?>, true)" class="flex-1 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    今天不再显示
                </button>
                <button onclick="closeCloudAnnouncement(<?php echo $announcement['id']; ?>, false)" class="flex-1 py-2 bg-gradient-to-r from-green-500 to-blue-500 text-white rounded-lg hover:opacity-90 transition">
                    我知道了
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>

    <script>
        function closeCloudAnnouncement(id, noShowToday) {
            const modal = document.getElementById('cloudAnnouncementModal_' + id);
            if (modal) {
                modal.remove();
            }
            if (noShowToday) {
                document.cookie = 'cloud_announcement_seen_' + id + '=1; expires=' + new Date(Date.now() + 86400000).toUTCString() + '; path=/';
            }
        }
    </script>
</body>
</html>
