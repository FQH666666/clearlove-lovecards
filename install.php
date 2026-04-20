<?php
require_once 'config.php';

if (INSTALLED) {
    header('Location: index.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $dbType = $_POST['db_type'] ?? 'sqlite';
        
        session_start();
        $_SESSION['install'] = [
            'db_type' => $dbType
        ];
        
        header('Location: install.php?step=2');
        exit;
    } elseif ($step === 2) {
        session_start();
        $dbType = $_SESSION['install']['db_type'];
        
        if ($dbType === 'mysql') {
            $dbHost = $_POST['db_host'] ?? 'localhost';
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            
            try {
                $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$dbName`");
                
                $_SESSION['install']['db_host'] = $dbHost;
                $_SESSION['install']['db_name'] = $dbName;
                $_SESSION['install']['db_user'] = $dbUser;
                $_SESSION['install']['db_pass'] = $dbPass;
                
                header('Location: install.php?step=3');
                exit;
            } catch (PDOException $e) {
                $error = '数据库连接失败: ' . $e->getMessage();
            }
        } else {
            // SQLite不需要额外配置，直接进入下一步
            header('Location: install.php?step=3');
            exit;
        }
    } elseif ($step === 3) {
        $siteName = $_POST['site_name'] ?? '校园表白墙';
        session_start();
        $_SESSION['install']['site_name'] = $siteName;
        header('Location: install.php?step=4');
        exit;
    } elseif ($step === 4) {
        $adminUser = $_POST['admin_user'] ?? '';
        $adminPass = $_POST['admin_pass'] ?? '';
        
        if (empty($adminUser) || empty($adminPass)) {
            $error = '请填写完整的管理员信息';
        } else {
            session_start();
            $config = $_SESSION['install'];
            
            try {
                if ($config['db_type'] === 'mysql') {
                    $pdo = new PDO(
                        "mysql:host=" . $config['db_host'] . ";dbname=" . $config['db_name'] . ";charset=utf8mb4",
                        $config['db_user'],
                        $config['db_pass'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    
                    $sql = "
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nickname VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    topic VARCHAR(100),
    media TEXT,
    likes INT DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS comments (
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

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    visit_date DATE NOT NULL,
    UNIQUE KEY unique_visit (ip, visit_date)
);
";
                } else {
                    // SQLite
                    $dbPath = __DIR__ . '/data.db';
                    $pdo = new PDO("sqlite:$dbPath", null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    
                    $sql = "
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nickname TEXT NOT NULL,
    content TEXT NOT NULL,
    topic TEXT,
    media TEXT,
    likes INTEGER DEFAULT 0,
    is_pinned INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    nickname TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_post_id ON comments (post_id);

CREATE TABLE IF NOT EXISTS topics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content TEXT NOT NULL,
    image TEXT,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL,
    visit_date DATE NOT NULL,
    UNIQUE (ip, visit_date)
);
";
                }
                
                $pdo->exec($sql);
                $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $stmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);
                
                $envContent = "<?php\n";
                $envContent .= "define('DB_TYPE', '" . $config['db_type'] . "');\n";
                if ($config['db_type'] === 'mysql') {
                    $envContent .= "define('DB_HOST', '" . addslashes($config['db_host']) . "');\n";
                    $envContent .= "define('DB_NAME', '" . addslashes($config['db_name']) . "');\n";
                    $envContent .= "define('DB_USER', '" . addslashes($config['db_user']) . "');\n";
                    $envContent .= "define('DB_PASS', '" . addslashes($config['db_pass']) . "');\n";
                }
                $envContent .= "define('SITE_NAME', '" . addslashes($config['site_name']) . "');\n";
                $envContent .= "define('INSTALLED', true);\n";
                
                file_put_contents(__DIR__ . '/.env.php', $envContent);
                
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                
                // SQLite数据库文件权限设置
                if ($config['db_type'] === 'sqlite' && file_exists(__DIR__ . '/data.db')) {
                    chmod(__DIR__ . '/data.db', 0666);
                }
                
                session_destroy();
                
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = '安装失败: ' . $e->getMessage();
            }
        }
    }
}?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - 校园表白墙</title>
    <link href="https://cdn.staticfile.net/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.staticfile.net/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #e3f2fd 100%);
            min-height: 100vh;
        }
        .step-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .step-active {
            background: linear-gradient(135deg, #66bb6a 0%, #42a5f5 100%);
            color: white;
        }
        .step-completed {
            background: #66bb6a;
            color: white;
        }
        .step-inactive {
            background: #e0e0e0;
            color: #999;
        }
        .db-card {
            border: 2px solid #e0e0e0;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .db-card:hover {
            border-color: #66bb6a;
            box-shadow: 0 4px 12px rgba(102, 187, 106, 0.15);
        }
        .db-card.active {
            border-color: #66bb6a;
            background: rgba(102, 187, 106, 0.05);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent mb-2">
                <i class="fas fa-heart mr-2"></i>校园表白墙
            </h1>
            <p class="text-gray-500">快速安装向导</p>
        </div>

        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center">
                <div class="step-indicator <?php echo $step >= 1 ? ($step > 1 ? 'step-completed' : 'step-active') : 'step-inactive'; ?>">
                    <?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?>
                </div>
                <div class="w-12 h-1 mx-2 <?php echo $step > 1 ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
                <div class="step-indicator <?php echo $step >= 2 ? ($step > 2 ? 'step-completed' : 'step-active') : 'step-inactive'; ?>">
                    <?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?>
                </div>
                <div class="w-12 h-1 mx-2 <?php echo $step > 2 ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
                <div class="step-indicator <?php echo $step >= 3 ? ($step > 3 ? 'step-completed' : 'step-active') : 'step-inactive'; ?>">
                    <?php echo $step > 3 ? '<i class="fas fa-check"></i>' : '3'; ?>
                </div>
                <div class="w-12 h-1 mx-2 <?php echo $step > 3 ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
                <div class="step-indicator <?php echo $step >= 4 ? 'step-active' : 'step-inactive'; ?>">4</div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php if ($step === 1): ?>
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-database mr-2 text-green-600"></i>数据库类型</h2>
                    <p class="text-gray-600 mb-4">请选择您要使用的数据库类型：</p>
                    
                    <div class="space-y-4">
                        <div class="db-card active" onclick="document.getElementById('db_type_sqlite').checked = true; document.querySelectorAll('.db-card').forEach(card => card.classList.remove('active')); this.classList.add('active');">
                            <div class="flex items-center">
                                <input type="radio" id="db_type_sqlite" name="db_type" value="sqlite" checked class="mr-3">
                                <div>
                                    <h3 class="font-semibold text-gray-800">SQLite</h3>
                                    <p class="text-sm text-gray-600 mt-1">轻量级嵌入式数据库，无需额外配置</p>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-green-50 rounded-lg">
                                <h4 class="font-medium text-green-800">优点：</h4>
                                <ul class="text-sm text-gray-700 list-disc pl-5 mt-1">
                                    <li>无需安装数据库服务</li>
                                    <li>配置简单，开箱即用</li>
                                    <li>文件型数据库，便于迁移</li>
                                    <li>适合中小型网站</li>
                                </ul>
                                <h4 class="font-medium text-red-800 mt-2">缺点：</h4>
                                <ul class="text-sm text-gray-700 list-disc pl-5 mt-1">
                                    <li>并发性能相对较低</li>
                                    <li>不适合大规模应用</li>
                                    <li>功能相对MySQL较少（不过这个项目够用）</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="db-card" onclick="document.getElementById('db_type_mysql').checked = true; document.querySelectorAll('.db-card').forEach(card => card.classList.remove('active')); this.classList.add('active');">
                            <div class="flex items-center">
                                <input type="radio" id="db_type_mysql" name="db_type" value="mysql" class="mr-3">
                                <div>
                                    <h3 class="font-semibold text-gray-800">MySQL</h3>
                                    <p class="text-sm text-gray-600 mt-1">功能强大的关系型数据库</p>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-green-50 rounded-lg">
                                <h4 class="font-medium text-green-800">优点：</h4>
                                <ul class="text-sm text-gray-700 list-disc pl-5 mt-1">
                                    <li>性能优异，适合高并发</li>
                                    <li>功能丰富，支持复杂查询</li>
                                    <li>适合大规模应用</li>
                                    <li>生态成熟，社区支持好</li>
                                </ul>
                                <h4 class="font-medium text-red-800 mt-2">缺点：</h4>
                                <ul class="text-sm text-gray-700 list-disc pl-5 mt-1">
                                    <li>需要单独安装数据库服务（一般宝塔/虚拟主机已安装）</li>
                                    <li>安装时需填入 数据库主机、数据库名称、数据库账号、数据库密码 等信息</li>
                                    <li>迁移不如SQLite方便</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($step === 2): ?>
                <?php session_start(); $dbType = $_SESSION['install']['db_type']; ?>
                <?php if ($dbType === 'mysql'): ?>
                    <div class="space-y-4">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-database mr-2 text-green-600"></i>MySQL数据库配置</h2>
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
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-database mr-2 text-green-600"></i>SQLite数据库配置</h2>
                        <p class="text-gray-600">SQLite数据库无需额外配置，将自动创建在项目根目录。</p>
                        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3 text-xl"></i>
                                <div>
                                    <h4 class="font-medium text-blue-800">提示</h4>
                                    <p class="text-sm text-gray-700 mt-1">SQLite数据库文件将被创建为 <code>data.db</code>，请确保服务器对该文件有写入权限。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ($step === 3): ?>
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-globe mr-2 text-blue-500"></i>网站配置</h2>
                    <div>
                        <label class="block text-gray-700 mb-2">网站名称</label>
                        <input type="text" name="site_name" value="校园表白墙" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                </div>
            <?php elseif ($step === 4): ?>
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-user-shield mr-2 text-purple-500"></i>管理员账号</h2>
                    <div>
                        <label class="block text-gray-700 mb-2">管理员账号</label>
                        <input type="text" name="admin_user" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">管理员密码</label>
                        <input type="password" name="admin_pass" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full mt-6 bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                <?php echo $step === 4 ? '完成安装' : '下一步'; ?>
                <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </form>
    </div>
</body>
</html>
