<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$messages = [];
$errors = [];

function logMessage($msg) {
    global $messages;
    $messages[] = $msg;
    echo "<script>document.getElementById('log').innerHTML += '" . addslashes($msg) . "<br>';</script>";
    ob_flush();
    flush();
}

function logError($msg) {
    global $errors;
    $errors[] = $msg;
    echo "<script>document.getElementById('log').innerHTML += '<span style=\"color:red;\'>" . addslashes($msg) . "</span><br>';</script>";
    ob_flush();
    flush();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $configMethod = $_POST['config_method'] ?? 'manual';
    
    if ($configMethod === 'auto') {
        $oldConfigPath = $_POST['config_path'] ?? '';
        
        if (empty($oldConfigPath) || !file_exists($oldConfigPath)) {
            $errors[] = "找不到config.php文件，请确认路径正确";
        } else {
            require $oldConfigPath;
            
            session_start();
            $_SESSION['upgrade'] = [
                'db_host' => $db_host,
                'db_name' => $db_name,
                'db_user' => $db_user,
                'db_pass' => $db_pass,
                'site_name' => $site_name
            ];
            
            header('Location: up.php?step=2');
            exit;
        }
    } else {
        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        $site_name = $_POST['site_name'] ?? '校园表白墙';
        
        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            $errors[] = "请填写完整的数据库配置";
        } else {
            session_start();
            $_SESSION['upgrade'] = [
                'db_host' => $db_host,
                'db_name' => $db_name,
                'db_user' => $db_user,
                'db_pass' => $db_pass,
                'site_name' => $site_name
            ];
            
            header('Location: up.php?step=2');
            exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    session_start();
    $config = $_SESSION['upgrade'];
    
    ob_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>升级 - 校园表白墙</title>
    <link href="https://cdn.staticfile.net/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #e3f2fd 100%);
            min-height: 100vh;
        }
        #log {
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 max-w-2xl w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent mb-2">
                <i class="fas fa-heart mr-2"></i>正在升级
            </h1>
            <p class="text-gray-500">请稍候，正在迁移数据...</p>
        </div>
        
        <div id="log" class="bg-gray-900 text-green-400 p-4 rounded-xl mb-6"></div>
        
        <div id="progress" class="hidden">
            <div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6">
                <i class="fas fa-check-circle mr-2"></i>升级完成！
            </div>
            <a href="index.php" class="w-full block text-center bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                前往首页 <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</body>
</html>
<?php
    $html = ob_get_clean();
    echo $html;
    ob_flush();
    flush();
    
    try {
        logMessage("连接数据库...");
        $pdo = new PDO(
            "mysql:host=" . $config['db_host'] . ";dbname=" . $config['db_name'] . ";charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        logMessage("✓ 数据库连接成功");
        
        logMessage("\n备份旧表...");
        $backupTables = ['posts', 'comments', 'likes', 'visits', 'admin'];
        foreach ($backupTables as $table) {
            $backupTable = $table . '_backup_' . date('YmdHis');
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$backupTable` LIKE `$table`");
            $pdo->exec("INSERT INTO `$backupTable` SELECT * FROM `$table`");
            logMessage("✓ 已备份 $table 到 $backupTable");
        }
        
        logMessage("\n创建新表结构...");
        $sql = "
CREATE TABLE IF NOT EXISTS new_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nickname VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    topic VARCHAR(100),
    media TEXT,
    likes INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS new_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    nickname VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_id (post_id)
);

CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    image VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS new_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS new_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    visit_date DATE NOT NULL,
    UNIQUE KEY unique_visit (ip, visit_date)
);
";
        $pdo->exec($sql);
        logMessage("✓ 新表结构创建成功");
        
        logMessage("\n迁移帖子数据...");
        $stmt = $pdo->query("SELECT * FROM posts");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $migratedPosts = 0;
        
        foreach ($posts as $post) {
            $likeCount = 0;
            $likeStmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
            $likeStmt->execute([$post['id']]);
            $likeCount = $likeStmt->fetch()['count'];
            
            $media = !empty($post['media']) ? $post['media'] : null;
            
            $insertStmt = $pdo->prepare("INSERT INTO new_posts (id, nickname, content, topic, media, likes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $post['id'],
                !empty($post['nickname']) ? $post['nickname'] : '匿名',
                $post['content'],
                null,
                $media,
                $likeCount,
                $post['created_at']
            ]);
            $migratedPosts++;
        }
        logMessage("✓ 成功迁移 $migratedPosts 条帖子");
        
        logMessage("\n迁移评论数据...");
        $stmt = $pdo->query("SELECT * FROM comments");
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $migratedComments = 0;
        
        foreach ($comments as $comment) {
            $insertStmt = $pdo->prepare("INSERT INTO new_comments (id, post_id, nickname, content, created_at) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $comment['id'],
                $comment['post_id'],
                !empty($comment['nickname']) ? $comment['nickname'] : '匿名',
                $comment['content'],
                $comment['created_at']
            ]);
            $migratedComments++;
        }
        logMessage("✓ 成功迁移 $migratedComments 条评论");
        
        logMessage("\n迁移管理员数据...");
        $stmt = $pdo->query("SELECT * FROM admin");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $migratedAdmins = 0;
        
        foreach ($admins as $admin) {
            $insertStmt = $pdo->prepare("INSERT INTO new_admins (id, username, password) VALUES (?, ?, ?)");
            $insertStmt->execute([
                $admin['id'],
                $admin['username'],
                $admin['password']
            ]);
            $migratedAdmins++;
        }
        logMessage("✓ 成功迁移 $migratedAdmins 个管理员账户");
        
        logMessage("\n迁移访问统计数据...");
        $stmt = $pdo->query("SELECT * FROM visits");
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $migratedVisits = 0;
        
        foreach ($visits as $visit) {
            $insertStmt = $pdo->prepare("INSERT INTO new_visits (id, ip, visit_date) VALUES (?, ?, ?)");
            $insertStmt->execute([
                $visit['id'],
                $visit['ip'],
                isset($visit['date']) ? $visit['date'] : $visit['visit_date']
            ]);
            $migratedVisits++;
        }
        logMessage("✓ 成功迁移 $migratedVisits 条访问记录");
        
        logMessage("\n替换旧表...");
        $tablesToReplace = [
            'posts' => 'new_posts',
            'comments' => 'new_comments',
            'admins' => 'new_admins',
            'visits' => 'new_visits'
        ];
        
        foreach ($tablesToReplace as $oldTable => $newTable) {
            $pdo->exec("DROP TABLE IF EXISTS `$oldTable`");
            $pdo->exec("RENAME TABLE `$newTable` TO `$oldTable`");
            logMessage("✓ 已替换 $oldTable");
        }
        
        logMessage("\n创建默认公告...");
        $pdo->exec("INSERT INTO announcements (content, active) VALUES ('欢迎使用新版表白墙！', 1)");
        logMessage("✓ 默认公告已创建");
        
        logMessage("\n创建配置文件...");
        $envContent = "<?php\n";
        $envContent .= "define('DB_HOST', '" . addslashes($config['db_host']) . "');\n";
        $envContent .= "define('DB_NAME', '" . addslashes($config['db_name']) . "');\n";
        $envContent .= "define('DB_USER', '" . addslashes($config['db_user']) . "');\n";
        $envContent .= "define('DB_PASS', '" . addslashes($config['db_pass']) . "');\n";
        $envContent .= "define('SITE_NAME', '" . addslashes($config['site_name']) . "');\n";
        $envContent .= "define('INSTALLED', true);\n";
        
        file_put_contents(__DIR__ . '/.env.php', $envContent);
        logMessage("✓ 配置文件已创建");
        
        logMessage("\n处理上传文件...");
        $newUploadsDir = __DIR__ . '/uploads';
        
        if (!is_dir($newUploadsDir)) {
            mkdir($newUploadsDir, 0755, true);
            logMessage("✓ 已创建uploads目录");
        }
        
        $oldUploadsDir = __DIR__ . '/../newbbq.kazx.top_ZfA4i(1)/uploads';
        if (is_dir($oldUploadsDir)) {
            $files = scandir($oldUploadsDir);
            $copiedFiles = 0;
            $skippedFiles = 0;
            
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($oldUploadsDir . '/' . $file)) {
                    $destFile = $newUploadsDir . '/' . $file;
                    if (!file_exists($destFile)) {
                        copy($oldUploadsDir . '/' . $file, $destFile);
                        $copiedFiles++;
                    } else {
                        $skippedFiles++;
                    }
                }
            }
            logMessage("✓ 已复制 $copiedFiles 个新文件");
            if ($skippedFiles > 0) {
                logMessage("ℹ 跳过 $skippedFiles 个已存在的文件");
            }
        } else {
            logMessage("ℹ 未找到旧版本uploads目录（如果是直接覆盖升级，文件应该已在uploads目录中）");
        }
        
        logMessage("\n========================================");
        logMessage("✓ 升级完成！");
        logMessage("========================================");
        logMessage("统计信息:");
        logMessage("- 帖子: $migratedPosts");
        logMessage("- 评论: $migratedComments");
        logMessage("- 管理员: $migratedAdmins");
        logMessage("- 访问记录: $migratedVisits");
        logMessage("\n重要提示:");
        logMessage("1. 旧表已备份为表名_backup_时间戳格式");
        logMessage("2. 请确认数据无误后再删除备份表");
        logMessage("3. 请删除up.php文件以确保安全");
        
        echo "<script>document.getElementById('progress').classList.remove('hidden');</script>";
        
    } catch (Exception $e) {
        logError("\n✗ 升级失败: " . $e->getMessage());
        logError("堆栈跟踪:\n" . $e->getTraceAsString());
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>升级 - 校园表白墙</title>
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
    <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent mb-2">
                <i class="fas fa-heart mr-2"></i>升级向导
            </h1>
            <p class="text-gray-500">从旧版本升级到新版表白墙</p>
        </div>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" id="configForm">
                <div class="space-y-4">
                    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl">
                        <i class="fas fa-info-circle mr-2"></i>
                        请选择配置方式
                    </div>
                    
                    <div class="space-y-3">
                        <label class="flex items-center p-4 border-2 rounded-xl cursor-pointer transition hover:border-blue-400 config-option" data-method="manual">
                            <input type="radio" name="config_method" value="manual" class="sr-only" checked>
                            <div class="flex-1">
                                <div class="font-semibold text-gray-800"><i class="fas fa-keyboard mr-2"></i>手动输入配置</div>
                                <div class="text-sm text-gray-500">适合旧版本config.php已被覆盖的情况</div>
                            </div>
                            <i class="fas fa-check-circle text-2xl text-transparent config-check"></i>
                        </label>
                        
                        <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer transition hover:border-blue-400 config-option" data-method="auto">
                            <input type="radio" name="config_method" value="auto" class="sr-only">
                            <div class="flex-1">
                                <div class="font-semibold text-gray-800"><i class="fas fa-file-import mr-2"></i>自动读取config.php</div>
                                <div class="text-sm text-gray-500">适合旧版本config.php还存在的情况</div>
                            </div>
                            <i class="fas fa-check-circle text-2xl text-transparent config-check"></i>
                        </label>
                    </div>
                    
                    <div id="manualConfig" class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-database mr-2 text-green-600"></i>数据库配置</h3>
                        <div>
                            <label class="block text-gray-700 mb-2">数据库主机</label>
                            <input type="text" name="db_host" value="localhost" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">数据库名称</label>
                            <input type="text" name="db_name" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">数据库账号</label>
                            <input type="text" name="db_user" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">数据库密码</label>
                            <input type="password" name="db_pass" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">网站名称</label>
                            <input type="text" name="site_name" value="校园表白墙" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                    </div>
                    
                    <div id="autoConfig" class="space-y-4 hidden">
                        <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-folder-open mr-2 text-blue-600"></i>config.php路径</h3>
                        <div>
                            <label class="block text-gray-700 mb-2">旧版本config.php完整路径</label>
                            <input type="text" name="config_path" placeholder="/www/wwwroot/旧版本目录/config.php" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <p class="text-sm text-gray-500 mt-2">例如：../newbbq.kazx.top_ZfA4i(1)/config.php</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full mt-6 bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                    下一步 <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
            
            <script>
                document.querySelectorAll('.config-option').forEach(option => {
                    option.addEventListener('click', function() {
                        const method = this.dataset.method;
                        
                        document.querySelectorAll('.config-option').forEach(opt => {
                            opt.classList.remove('border-blue-500', 'bg-blue-50');
                            opt.classList.add('border-gray-200');
                            opt.querySelector('.config-check').classList.add('text-transparent');
                        });
                        
                        this.classList.add('border-blue-500', 'bg-blue-50');
                        this.classList.remove('border-gray-200');
                        this.querySelector('.config-check').classList.remove('text-transparent');
                        this.querySelector('.config-check').classList.add('text-blue-500');
                        
                        this.querySelector('input[type="radio"]').checked = true;
                        
                        if (method === 'manual') {
                            document.getElementById('manualConfig').classList.remove('hidden');
                            document.getElementById('autoConfig').classList.add('hidden');
                        } else {
                            document.getElementById('manualConfig').classList.add('hidden');
                            document.getElementById('autoConfig').classList.remove('hidden');
                        }
                    });
                });
                
                document.querySelector('.config-option[data-method="manual"]').click();
            </script>
        <?php elseif ($step === 2): ?>
            <?php
            session_start();
            $config = $_SESSION['upgrade'];
            ?>
            <form method="POST">
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-check-circle mr-2 text-green-600"></i>配置确认
                    </h2>
                    
                    <div class="bg-gray-50 p-4 rounded-xl space-y-2">
                        <p><strong>数据库主机:</strong> <?php echo htmlspecialchars($config['db_host']); ?></p>
                        <p><strong>数据库名称:</strong> <?php echo htmlspecialchars($config['db_name']); ?></p>
                        <p><strong>数据库用户:</strong> <?php echo htmlspecialchars($config['db_user']); ?></p>
                        <p><strong>网站名称:</strong> <?php echo htmlspecialchars($config['site_name']); ?></p>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-xl">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>重要提示:</strong>
                        <ul class="list-disc list-inside mt-2 text-sm">
                            <li>升级前会自动备份所有旧表</li>
                            <li>请确保有足够的数据库权限</li>
                            <li>升级过程中请勿关闭页面</li>
                        </ul>
                    </div>
                </div>

                <button type="submit" class="w-full mt-6 bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-4 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                    确认并开始迁移 <i class="fas fa-database ml-2"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
