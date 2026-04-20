<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$db = getDB();
if (!$db) {
    echo json_encode(['success' => false, 'error' => '未安装']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'like') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $liked = isset($_COOKIE['liked_' . $postId]);
    
    if (!$liked) {
        $db->exec("UPDATE posts SET likes = likes + 1 WHERE id = $postId");
        setcookie('liked_' . $postId, '1', time() + 86400 * 30, '/');
    }
    
    $stmt = $db->query("SELECT likes FROM posts WHERE id = $postId");
    $post = $stmt->fetch();
    
    echo json_encode(['success' => true, 'likes' => $post['likes']]);
    exit;
}

if ($action === 'comment') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $nickname = $db->quote($_POST['nickname'] ?? '匿名用户');
    $content = $db->quote($_POST['content'] ?? '');
    
    if (empty($_POST['content'])) {
        echo json_encode(['success' => false, 'error' => '评论内容不能为空']);
        exit;
    }
    
    $db->exec("INSERT INTO comments (post_id, nickname, content) VALUES ($postId, $nickname, $content)");
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'upload') {
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => '没有文件']);
        exit;
    }
    
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // 严格白名单检查
    $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedVideoExts = ['mp4', 'webm', 'mov'];
    $allowedExts = array_merge($allowedImageExts, $allowedVideoExts);
    
    if (!in_array($ext, $allowedExts)) {
        echo json_encode(['success' => false, 'error' => '不支持的文件类型']);
        exit;
    }
    
    $type = in_array($ext, $allowedImageExts) ? 'image' : 'video';
    
    // 文件大小限制
    $maxVideoSize = 120 * 1024 * 1024; // 120MB
    $maxImageSize = 50 * 1024 * 1024;  // 50MB
    
    if ($type === 'video' && $file['size'] > $maxVideoSize) {
        echo json_encode(['success' => false, 'error' => '视频大小不能超过120MB']);
        exit;
    }
    
    if ($type === 'image' && $file['size'] > $maxImageSize) {
        echo json_encode(['success' => false, 'error' => '图片大小不能超过50MB']);
        exit;
    }
    
    // 文件名随机化
    $fileName = uniqid() . '.' . $ext;
    $uploadPath = UPLOAD_DIR . '/' . $fileName;
    
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        
        if ($type === 'image') {
            $webpPath = convertToWebp($uploadPath);
            if ($webpPath) {
                $fileName = pathinfo($webpPath, PATHINFO_BASENAME);
            }
        }
        
        echo json_encode(['success' => true, 'file' => $fileName, 'type' => $type]);
    } else {
        echo json_encode(['success' => false, 'error' => '上传失败']);
    }
    exit;
}

if ($action === 'upload_music') {
    if (!isset($_FILES['bg_music_file'])) {
        echo json_encode(['success' => false, 'error' => '没有文件']);
        exit;
    }
    
    $file = $_FILES['bg_music_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // 检查文件类型
    $allowedExts = ['mp3', 'wav', 'ogg', 'm4a'];
    if (!in_array($ext, $allowedExts)) {
        echo json_encode(['success' => false, 'error' => '不支持的文件类型']);
        exit;
    }
    
    // 检查文件大小
    $maxSize = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => '文件大小不能超过50MB']);
        exit;
    }
    
    $fileName = uniqid() . '.' . $ext;
    $uploadPath = UPLOAD_DIR . '/' . $fileName;
    
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // 更新配置文件
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
            'bg_music_enabled' => true,
            'bg_music_file' => $fileName,
            'bg_music_volume' => defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50
        ]);
        
        echo json_encode(['success' => true, 'file' => $fileName]);
    } else {
        echo json_encode(['success' => false, 'error' => '上传失败']);
    }
    exit;
}

if ($action === 'captcha') {
    session_start();
    
    $width = 150;
    $height = 50;
    $image = imagecreatetruecolor($width, $height);
    
    $bgColor = imagecolorallocate($image, 240, 248, 240);
    imagefill($image, 0, 0, $bgColor);
    
    $code = '';
    for ($i = 0; $i < 4; $i++) {
        $num = rand(0, 9);
        $code .= $num;
        
        $x = 20 + $i * 32;
        $y = rand(30, 40);
        
        $fontColor = imagecolorallocate($image, rand(30, 80), rand(80, 130), rand(30, 80));
        imagestring($image, 5, $x, $y - 15, (string)$num, $fontColor);
    }
    
    for ($i = 0; $i < 3; $i++) {
        $lineColor = imagecolorallocate($image, rand(180, 220), rand(200, 240), rand(180, 220));
        imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lineColor);
    }
    
    for ($i = 0; $i < 30; $i++) {
        $pixelColor = imagecolorallocate($image, rand(150, 200), rand(180, 220), rand(150, 200));
        imagesetpixel($image, rand(0, $width), rand(0, $height), $pixelColor);
    }
    
    $_SESSION['captcha_code'] = $code;
    
    ob_start();
    imagepng($image);
    $imageData = ob_get_clean();
    imagedestroy($image);
    
    echo json_encode([
        'success' => true,
        'image' => 'data:image/png;base64,' . base64_encode($imageData)
    ]);
    exit;
}

if ($action === 'verify_captcha') {
    session_start();
    $inputCode = trim($_POST['code'] ?? '');
    $correctCode = $_SESSION['captcha_code'] ?? '';
    
    if ($inputCode === $correctCode) {
        $_SESSION['captcha_verified'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => '验证码错误']);
    }
    exit;
}

if ($action === 'get_cloud_sensitive_words') {
    $result = getCloudSensitiveWords();
    
    if (isset($result['error'])) {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    } else {
        echo json_encode(['success' => true, 'words' => $result['words']]);
    }
    exit;
}

if ($action === 'check_update') {
    require_once 'functions.php';
    
    $updateInfo = checkUpdate();
    
    if (isset($updateInfo['error'])) {
        echo json_encode(['success' => false, 'error' => $updateInfo['error']]);
    } else {
        echo json_encode(['success' => true, 'has_update' => $updateInfo['has_update'], 'version' => $updateInfo['version'], 'release_notes' => $updateInfo['release_notes'], 'download_url' => $updateInfo['download_url']]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => '无效操作']);
