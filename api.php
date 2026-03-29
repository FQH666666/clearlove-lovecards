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
    $type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'video';
    
    $maxVideoSize = 120 * 1024 * 1024;
    if ($type === 'video' && $file['size'] > $maxVideoSize) {
        echo json_encode(['success' => false, 'error' => '视频大小不能超过120MB']);
        exit;
    }
    
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

echo json_encode(['success' => false, 'error' => '无效操作']);
