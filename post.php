<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'lunar/Lunar.php';

use com\nlf\calendar\Solar;

$db = getDB();
if (!$db) {
    header('Location: install.php');
    exit;
}

$topics = getTopics($db);
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        session_start();
        if (!isset($_SESSION['captcha_verified']) || !$_SESSION['captcha_verified']) {
            $error = '请先完成验证';
        } else {
            $nickname = trim($_POST['nickname'] ?? '') ?: '匿名用户';
            $content = trim($_POST['content'] ?? '');
            $topic = trim($_POST['topic'] ?? '');
            $media = json_decode($_POST['media'] ?? '[]', true);
            
            if (empty($content)) {
                $error = '请输入帖子内容';
            } else {
                // 检查敏感词
                if (defined('PURE_MODE') && PURE_MODE && defined('SENSITIVE_WORDS') && SENSITIVE_WORDS) {
                    $sensitiveWords = explode(' ', SENSITIVE_WORDS);
                    foreach ($sensitiveWords as $word) {
                        if (!empty($word) && strpos($content, $word) !== false) {
                            $error = '出现"' . $word . '"敏感词，请修改后发布';
                            break;
                        }
                    }
                }
                
                if (!isset($error)) {
                    if ($topic) {
                        if (DB_TYPE === 'mysql') {
                            $stmt = $db->prepare("INSERT IGNORE INTO topics (name) VALUES (?)");
                        } else {
                            // SQLite使用INSERT OR IGNORE
                            $stmt = $db->prepare("INSERT OR IGNORE INTO topics (name) VALUES (?)");
                        }
                        $stmt->execute([$topic]);
                    }
                    
                    $stmt = $db->prepare("INSERT INTO posts (nickname, content, topic, media) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nickname, $content, $topic, json_encode($media)]);
                    
                    $_SESSION['captcha_verified'] = false;
                    $success = true;
                }
            }
        }
    }
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布帖子 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.staticfile.net/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.staticfile.net/font-awesome/6.5.1/css/all.min.css">
    <style>
        <?php
        // 检查是否是节日
        function isHoliday() {
            // 获取当前日期
            $solar = Solar::fromDate(new DateTime());
            $today = date('md');
            
            // 公历节日
            $solarHolidays = [
                '0101' => 'spring', // 元旦
                '0214' => 'valentine', // 情人节
                '0501' => 'mayday', // 劳动节
                '0601' => 'children', // 儿童节
                '1001' => 'national', // 国庆节
                '1225' => 'christmas' // 圣诞节
            ];
            
            // 检查公历节日
            if (isset($solarHolidays[$today])) {
                return $solarHolidays[$today];
            }
            
            // 获取节日列表
            $festivals = $solar->getFestivals();
            
            // 检查农历节日
            foreach ($festivals as $festival) {
                switch ($festival) {
                    case '春节':
                    case '大年初一':
                        return 'spring';
                    case '清明节':
                        return 'qingming';
                    case '端午节':
                        return 'dragon';
                    case '七夕节':
                        return 'qixi';
                    case '中秋节':
                        return 'midautumn';
                }
            }
            
            return false;
        }
        
        // 主题颜色配置
        $themes = [
            'default' => ['colors' => '#66bb6a,#42a5f5', 'bg' => '#e8f5e9,#e3f2fd', 'border' => '#a5d6a7', 'hover' => '#66bb6a', 'hoverBg' => '102,187,106', 'progress' => '#66bb6a,#42a5f5', 'captcha' => '102,187,106'],
            'pink' => ['colors' => '#ec407a,#ff80ab', 'bg' => '#fce4ec,#f8bbd0', 'border' => '#f48fb1', 'hover' => '#ec407a', 'hoverBg' => '236,64,122', 'progress' => '#ec407a,#ff80ab', 'captcha' => '236,64,122'],
            'purple' => ['colors' => '#7e57c2,#ab47bc', 'bg' => '#ede7f6,#e1bee7', 'border' => '#ce93d8', 'hover' => '#7e57c2', 'hoverBg' => '126,87,194', 'progress' => '#7e57c2,#ab47bc', 'captcha' => '126,87,194'],
            'orange' => ['colors' => '#ffa726,#ff7043', 'bg' => '#fff3e0,#ffe0b2', 'border' => '#ffcc80', 'hover' => '#ffa726', 'hoverBg' => '255,167,38', 'progress' => '#ffa726,#ff7043', 'captcha' => '255,167,38'],
            'red' => ['colors' => '#ef5350,#e53935', 'bg' => '#ffebee,#ffcdd2', 'border' => '#ef9a9a', 'hover' => '#ef5350', 'hoverBg' => '239,83,80', 'progress' => '#ef5350,#e53935', 'captcha' => '239,83,80'],
            'teal' => ['colors' => '#26c6da,#00acc1', 'bg' => '#e0f7fa,#b2ebf2', 'border' => '#80deea', 'hover' => '#26c6da', 'hoverBg' => '38,198,218', 'progress' => '#26c6da,#00acc1', 'captcha' => '38,198,218'],
            'amber' => ['colors' => '#ffca28,#ff9800', 'bg' => '#fff8e1,#ffecb3', 'border' => '#ffd54f', 'hover' => '#ffca28', 'hoverBg' => '255,202,40', 'progress' => '#ffca28,#ff9800', 'captcha' => '255,202,40'],
            'indigo' => ['colors' => '#3f51b5,#5c6bc0', 'bg' => '#e8eaf6,#c5cae9', 'border' => '#9fa8da', 'hover' => '#3f51b5', 'hoverBg' => '63,81,181', 'progress' => '#3f51b5,#5c6bc0', 'captcha' => '63,81,181'],
            'deep-purple' => ['colors' => '#5e35b1,#4527a0', 'bg' => '#ede7f6,#d1c4e9', 'border' => '#b39ddb', 'hover' => '#5e35b1', 'hoverBg' => '94,53,177', 'progress' => '#5e35b1,#4527a0', 'captcha' => '94,53,177'],
            'light-blue' => ['colors' => '#29b6f6,#03a9f4', 'bg' => '#e3f2fd,#bbdefb', 'border' => '#81d4fa', 'hover' => '#29b6f6', 'hoverBg' => '41,182,246', 'progress' => '#29b6f6,#03a9f4', 'captcha' => '41,182,246'],
            'lime' => ['colors' => '#cddc39,#8bc34a', 'bg' => '#f1f8e9,#dcedc8', 'border' => '#dce775', 'hover' => '#cddc39', 'hoverBg' => '205,220,57', 'progress' => '#cddc39,#8bc34a', 'captcha' => '205,220,57'],
            'cyan' => ['colors' => '#00bcd4,#0097a7', 'bg' => '#e0f7fa,#b2ebf2', 'border' => '#80deea', 'hover' => '#00bcd4', 'hoverBg' => '0,188,212', 'progress' => '#00bcd4,#0097a7', 'captcha' => '0,188,212'],
            'teal-green' => ['colors' => '#66bb6a,#43a047', 'bg' => '#e8f5e9,#c8e6c9', 'border' => '#a5d6a7', 'hover' => '#66bb6a', 'hoverBg' => '102,187,106', 'progress' => '#66bb6a,#43a047', 'captcha' => '102,187,106'],
            'purple-pink' => ['colors' => '#9c27b0,#e91e63', 'bg' => '#f3e5f5,#fce4ec', 'border' => '#ce93d8', 'hover' => '#9c27b0', 'hoverBg' => '156,39,176', 'progress' => '#9c27b0,#e91e63', 'captcha' => '156,39,176'],
            'blue-indigo' => ['colors' => '#1e88e5,#3949ab', 'bg' => '#e3f2fd,#e8eaf6', 'border' => '#64b5f6', 'hover' => '#1e88e5', 'hoverBg' => '30,136,229', 'progress' => '#1e88e5,#3949ab', 'captcha' => '30,136,229'],
            'orange-red' => ['colors' => '#ff9800,#f44336', 'bg' => '#fff3e0,#ffebee', 'border' => '#ffcc80', 'hover' => '#ff9800', 'hoverBg' => '255,152,0', 'progress' => '#ff9800,#f44336', 'captcha' => '255,152,0'],
            'green-teal' => ['colors' => '#4caf50,#009688', 'bg' => '#e8f5e9,#e0f2f1', 'border' => '#a5d6a7', 'hover' => '#4caf50', 'hoverBg' => '76,175,80', 'progress' => '#4caf50,#009688', 'captcha' => '76,175,80'],
            'blue-cyan' => ['colors' => '#2196f3,#00bcd4', 'bg' => '#e3f2fd,#e0f7fa', 'border' => '#64b5f6', 'hover' => '#2196f3', 'hoverBg' => '33,150,243', 'progress' => '#2196f3,#00bcd4', 'captcha' => '33,150,243'],
            'purple-indigo' => ['colors' => '#7b1fa2,#303f9f', 'bg' => '#f3e5f5,#e8eaf6', 'border' => '#ba68c8', 'hover' => '#7b1fa2', 'hoverBg' => '123,31,162', 'progress' => '#7b1fa2,#303f9f', 'captcha' => '123,31,162'],
            'pink-red' => ['colors' => '#e91e63,#c2185b', 'bg' => '#fce4ec,#ffebee', 'border' => '#f48fb1', 'hover' => '#e91e63', 'hoverBg' => '233,30,99', 'progress' => '#e91e63,#c2185b', 'captcha' => '233,30,99'],
            'amber-orange' => ['colors' => '#ffb300,#ff7043', 'bg' => '#fff8e1,#fff3e0', 'border' => '#ffd54f', 'hover' => '#ffb300', 'hoverBg' => '255,179,0', 'progress' => '#ffb300,#ff7043', 'captcha' => '255,179,0'],
            'teal-cyan' => ['colors' => '#26a69a,#00acc1', 'bg' => '#e0f2f1,#e0f7fa', 'border' => '#80cbc4', 'hover' => '#26a69a', 'hoverBg' => '38,166,154', 'progress' => '#26a69a,#00acc1', 'captcha' => '38,166,154'],
            'indigo-purple' => ['colors' => '#536dfe,#7b1fa2', 'bg' => '#ede7f6,#f3e5f5', 'border' => '#9fa8da', 'hover' => '#536dfe', 'hoverBg' => '83,109,254', 'progress' => '#536dfe,#7b1fa2', 'captcha' => '83,109,254'],
            'green-lime' => ['colors' => '#4caf50,#cddc39', 'bg' => '#e8f5e9,#f1f8e9', 'border' => '#a5d6a7', 'hover' => '#4caf50', 'hoverBg' => '76,175,80', 'progress' => '#4caf50,#cddc39', 'captcha' => '76,175,80'],
            'christmas' => ['colors' => '#e53935,#43a047', 'bg' => '#ffebee,#e8f5e9', 'border' => '#ef9a9a', 'hover' => '#e53935', 'hoverBg' => '229,57,53', 'progress' => '#e53935,#43a047', 'captcha' => '229,57,53'],
            'valentine' => ['colors' => '#e91e63,#c2185b', 'bg' => '#fce4ec,#f48fb1', 'border' => '#f48fb1', 'hover' => '#e91e63', 'hoverBg' => '233,30,99', 'progress' => '#e91e63,#c2185b', 'captcha' => '233,30,99'],
            'spring' => ['colors' => '#e53935,#ffb300', 'bg' => '#ffebee,#fff8e1', 'border' => '#ef9a9a', 'hover' => '#e53935', 'hoverBg' => '229,57,53', 'progress' => '#e53935,#ffb300', 'captcha' => '229,57,53'],
            'mayday' => ['colors' => '#4caf50,#81c784', 'bg' => '#e8f5e9,#c8e6c9', 'border' => '#a5d6a7', 'hover' => '#4caf50', 'hoverBg' => '76,175,80', 'progress' => '#4caf50,#81c784', 'captcha' => '76,175,80'],
            'children' => ['colors' => '#ff9800,#ffb74d', 'bg' => '#fff3e0,#ffe0b2', 'border' => '#ffcc80', 'hover' => '#ff9800', 'hoverBg' => '255,152,0', 'progress' => '#ff9800,#ffb74d', 'captcha' => '255,152,0'],
            'midautumn' => ['colors' => '#9c27b0,#ba68c8', 'bg' => '#f3e5f5,#e1bee7', 'border' => '#ce93d8', 'hover' => '#9c27b0', 'hoverBg' => '156,39,176', 'progress' => '#9c27b0,#ba68c8', 'captcha' => '156,39,176'],
            'national' => ['colors' => '#e53935,#ff5722', 'bg' => '#ffebee,#fbe9e7', 'border' => '#ef9a9a', 'hover' => '#e53935', 'hoverBg' => '229,57,53', 'progress' => '#e53935,#ff5722', 'captcha' => '229,57,53'],
            'qingming' => ['colors' => '#4caf50,#81c784', 'bg' => '#e8f5e9,#c8e6c9', 'border' => '#a5d6a7', 'hover' => '#4caf50', 'hoverBg' => '76,175,80', 'progress' => '#4caf50,#81c784', 'captcha' => '76,175,80'],
            'dragon' => ['colors' => '#e53935,#ff9800', 'bg' => '#ffebee,#fff3e0', 'border' => '#ef9a9a', 'hover' => '#e53935', 'hoverBg' => '229,57,53', 'progress' => '#e53935,#ff9800', 'captcha' => '229,57,53'],
            'qixi' => ['colors' => '#e91e63,#9c27b0', 'bg' => '#fce4ec,#f3e5f5', 'border' => '#f48fb1', 'hover' => '#e91e63', 'hoverBg' => '233,30,99', 'progress' => '#e91e63,#9c27b0', 'captcha' => '233,30,99']
        ];
        
        $theme = defined('THEME') ? THEME : 'default';
        $customColors = defined('CUSTOM_COLORS') ? CUSTOM_COLORS : '#66bb6a,#42a5f5';
        $themeAutoSwitch = defined('THEME_AUTO_SWITCH') ? THEME_AUTO_SWITCH : 'off';
        
        // 检查节日
        $holiday = isHoliday();
        if ($holiday) {
            $theme = $holiday;
        } else {
            // 自动切换主题
            if ($themeAutoSwitch === 'daily') {
                // 每天切换主题
                $dayOfYear = date('z');
                $themeKeys = array_keys($themes);
                // 排除节日主题和自定义主题
                $availableThemes = array_filter($themeKeys, function($key) {
                    return !in_array($key, ['christmas', 'valentine', 'spring', 'custom']);
                });
                $availableThemes = array_values($availableThemes);
                $themeIndex = $dayOfYear % count($availableThemes);
                $theme = $availableThemes[$themeIndex];
            } elseif ($themeAutoSwitch === 'random') {
                // 随机切换主题
                $themeKeys = array_keys($themes);
                // 排除节日主题和自定义主题
                $availableThemes = array_filter($themeKeys, function($key) {
                    return !in_array($key, ['christmas', 'valentine', 'spring', 'custom']);
                });
                $availableThemes = array_values($availableThemes);
                $theme = $availableThemes[array_rand($availableThemes)];
            }
        }
        
        if ($theme === 'custom') {
            $colors = explode(',', $customColors);
            $primaryColor = $colors[0] ?? '#66bb6a';
            $secondaryColor = $colors[1] ?? '#42a5f5';
            $bgColors = adjustBrightness($primaryColor, 40) . ',' . adjustBrightness($secondaryColor, 40);
            $borderColor = adjustBrightness($primaryColor, 20);
            $hoverColor = $primaryColor;
            $hoverBgColor = hexToRgb($primaryColor);
            $progressColors = $primaryColor . ',' . $secondaryColor;
            $captchaColor = hexToRgb($primaryColor);
        } else {
            $themeConfig = $themes[$theme] ?? $themes['default'];
            $primaryColor = explode(',', $themeConfig['colors'])[0];
            $secondaryColor = explode(',', $themeConfig['colors'])[1];
            $bgColors = $themeConfig['bg'];
            $borderColor = $themeConfig['border'];
            $hoverColor = $themeConfig['hover'];
            $hoverBgColor = $themeConfig['hoverBg'];
            $progressColors = $themeConfig['progress'];
            $captchaColor = $themeConfig['captcha'];
        }
        
        function hexToRgb($hex) {
            $hex = str_replace('#', '', $hex);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return "$r,$g,$b";
        }
        
        function adjustBrightness($hex, $steps) {
            $hex = str_replace('#', '', $hex);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            $r = max(0, min(255, $r + $steps));
            $g = max(0, min(255, $g + $steps));
            $b = max(0, min(255, $b + $steps));
            
            return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        }
        ?>
        body {
            background: linear-gradient(135deg, <?php echo $bgColors; ?>);
            min-height: 100vh;
        }
        .upload-area {
            border: 2px dashed <?php echo $borderColor; ?>;
            transition: all 0.3s ease;
        }
        .upload-area:hover, .upload-area.drag-over {
            border-color: <?php echo $hoverColor; ?>;
            background: rgba(<?php echo $hoverBgColor; ?>, 0.1);
        }
        .progress-bar {
            height: 4px;
            background: linear-gradient(90deg, <?php echo $progressColors; ?>);
            transition: width 0.3s ease;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .captcha-container {
            position: relative;
            cursor: pointer;
        }
        .captcha-marker {
            position: absolute;
            width: 30px;
            height: 30px;
            background: rgba(<?php echo $captchaColor; ?>, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        
        /* 节日背景效果 */
        body.spring::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(255, 179, 0, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 80% 20%, rgba(255, 179, 0, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 20% 80%, rgba(255, 179, 0, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 80% 80%, rgba(255, 179, 0, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 50% 50%, rgba(255, 179, 0, 0.3) 0%, transparent 10%);
            z-index: -1;
            animation: lanterns 4s ease-in-out infinite;
        }
        
        body.christmas::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 30% 10%, rgba(255, 255, 255, 0.8) 0%, transparent 5%),
                radial-gradient(circle at 70% 15%, rgba(255, 255, 255, 0.6) 0%, transparent 4%),
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.7) 0%, transparent 3%),
                radial-gradient(circle at 80% 40%, rgba(255, 255, 255, 0.5) 0%, transparent 4%),
                radial-gradient(circle at 50% 20%, rgba(255, 255, 255, 0.9) 0%, transparent 6%);
            z-index: -1;
            animation: snow 10s linear infinite;
        }
        
        body.valentine::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(233, 30, 99, 0.3) 0%, transparent 15%),
                radial-gradient(circle at 75% 25%, rgba(233, 30, 99, 0.3) 0%, transparent 15%),
                radial-gradient(circle at 25% 75%, rgba(233, 30, 99, 0.3) 0%, transparent 15%),
                radial-gradient(circle at 75% 75%, rgba(233, 30, 99, 0.3) 0%, transparent 15%);
            z-index: -1;
            animation: hearts 3s ease-in-out infinite;
        }
        
        body.midautumn::before {
            content: '';
            position: fixed;
            top: 10%;
            right: 10%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255, 255, 224, 0.9) 0%, rgba(255, 255, 153, 0.7) 70%, transparent 100%);
            border-radius: 50%;
            z-index: -1;
            box-shadow: 0 0 150px rgba(255, 255, 153, 0.6);
            animation: moon 20s linear infinite;
        }
        
        body.midautumn::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 30% 30%, rgba(255, 255, 153, 0.2) 0%, transparent 10%),
                radial-gradient(circle at 70% 30%, rgba(255, 255, 153, 0.2) 0%, transparent 10%),
                radial-gradient(circle at 30% 70%, rgba(255, 255, 153, 0.2) 0%, transparent 10%),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 153, 0.2) 0%, transparent 10%);
            z-index: -1;
            animation: twinkling 3s ease-in-out infinite;
        }
        
        body.mayday::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 20%),
                radial-gradient(circle at 80% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 20%),
                radial-gradient(circle at 50% 70%, rgba(76, 175, 80, 0.4) 0%, transparent 20%);
            z-index: -1;
            animation: leaves 6s ease-in-out infinite;
        }
        
        body.children::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(255, 152, 0, 0.4) 0%, transparent 12%),
                radial-gradient(circle at 85% 15%, rgba(255, 152, 0, 0.4) 0%, transparent 12%),
                radial-gradient(circle at 15% 85%, rgba(255, 152, 0, 0.4) 0%, transparent 12%),
                radial-gradient(circle at 85% 85%, rgba(255, 152, 0, 0.4) 0%, transparent 12%),
                radial-gradient(circle at 50% 50%, rgba(255, 152, 0, 0.4) 0%, transparent 12%);
            z-index: -1;
            animation: balloons 5s ease-in-out infinite;
        }
        
        body.national::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(229, 57, 53, 0.3) 0%, transparent 15%),
                radial-gradient(circle at 75% 25%, rgba(229, 57, 53, 0.3) 0%, transparent 15%),
                radial-gradient(circle at 25% 75%, rgba(229, 57, 53, 0.3) 0%, transparent 15%),
                radial-gradient(circle at 75% 75%, rgba(229, 57, 53, 0.3) 0%, transparent 15%);
            z-index: -1;
            animation: fireworks 2s ease-in-out infinite;
        }
        
        body.qingming::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 80% 30%, rgba(76, 175, 80, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 50% 70%, rgba(76, 175, 80, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 30% 60%, rgba(76, 175, 80, 0.3) 0%, transparent 10%),
                radial-gradient(circle at 70% 60%, rgba(76, 175, 80, 0.3) 0%, transparent 10%);
            z-index: -1;
            animation: leaves 6s ease-in-out infinite;
        }
        
        body.dragon::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(229, 57, 53, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 75% 25%, rgba(229, 57, 53, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 25% 75%, rgba(229, 57, 53, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 75% 75%, rgba(229, 57, 53, 0.4) 0%, transparent 15%),
                radial-gradient(circle at 50% 50%, rgba(255, 152, 0, 0.4) 0%, transparent 15%);
            z-index: -1;
            animation: dragonDance 4s ease-in-out infinite;
        }
        
        body.qixi::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(233, 30, 99, 0.3) 0%, transparent 12%),
                radial-gradient(circle at 80% 20%, rgba(233, 30, 99, 0.3) 0%, transparent 12%),
                radial-gradient(circle at 20% 80%, rgba(233, 30, 99, 0.3) 0%, transparent 12%),
                radial-gradient(circle at 80% 80%, rgba(233, 30, 99, 0.3) 0%, transparent 12%),
                radial-gradient(circle at 50% 50%, rgba(156, 39, 176, 0.3) 0%, transparent 12%);
            z-index: -1;
            animation: stars 3s ease-in-out infinite;
        }
        
        @keyframes lanterns {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes snow {
            0% { transform: translateY(-100vh); }
            100% { transform: translateY(100vh); }
        }
        
        @keyframes hearts {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
        }
        
        @keyframes moon {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes leaves {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(5deg); }
        }
        
        @keyframes balloons {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-10px) rotate(5deg); }
            50% { transform: translateY(-5px) rotate(0deg); }
            75% { transform: translateY(-10px) rotate(-5deg); }
        }
        
        @keyframes fireworks {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.8; }
        }
        
        @keyframes twinkling {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.8; }
        }
        
        @keyframes dragonDance {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-10px) rotate(2deg); }
            50% { transform: translateY(-5px) rotate(0deg); }
            75% { transform: translateY(-10px) rotate(-2deg); }
        }
        
        @keyframes stars {
            0%, 100% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.1); opacity: 1; }
        }
    </style>
</head>
<body class="p-4<?php if ($holiday) echo ' ' . $holiday; ?>">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-xl p-6 mb-6">
            <div class="flex items-center mb-6">
                <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent">
                    <i class="fas fa-pen mr-2"></i>发布帖子
                </h1>
            </div>

            <?php if ($success): ?>
                <div class="text-center py-10">
                    <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">发布成功！</h2>
                    <p class="text-gray-500 mb-6">您的帖子已成功发布</p>
                    <a href="index.php" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-8 py-3 rounded-xl font-semibold">
                        返回首页
                    </a>
                </div>
            <?php else: ?>
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form id="postForm" method="POST">
                    <div class="mb-6">
                        <label class="block text-gray-700 mb-2 font-medium">
                            <i class="fas fa-user mr-2 text-green-600"></i>昵称
                        </label>
                        <input type="text" name="nickname" placeholder="不填则为匿名" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 mb-2 font-medium">
                            <i class="fas fa-comment-dots mr-2 text-blue-500"></i>内容
                        </label>
                        <textarea name="content" id="content" maxlength="2000" rows="6" placeholder="说说你想说的..." class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none"></textarea>
                        <div class="text-right text-gray-400 text-sm mt-1"><span id="charCount">0</span>/2000</div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 mb-2 font-medium">
                            <i class="fas fa-images mr-2 text-purple-500"></i>图片/视频
                        </label>
                        <div id="uploadArea" class="upload-area rounded-xl p-6 text-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-green-400 mb-3"></i>
                            <p class="text-gray-500">点击或拖拽上传</p>
                            <p class="text-gray-400 text-sm mt-1">最多8个文件</p>
                            <input type="file" id="fileInput" multiple accept="image/*,video/*" class="hidden">
                        </div>
                        <div id="uploadProgress" class="hidden mt-3">
                            <div class="bg-gray-200 rounded-full h-1 overflow-hidden">
                                <div id="progressBar" class="progress-bar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div id="mediaPreview" class="grid grid-cols-4 gap-3 mt-4"></div>
                        <input type="hidden" name="media" id="mediaInput" value="[]">
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 mb-2 font-medium">
                            <i class="fas fa-hashtag mr-2 text-yellow-500"></i>话题
                        </label>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <?php foreach ($topics as $t): ?>
                                <button type="button" class="topic-tag px-3 py-1 bg-gray-100 rounded-full text-sm text-gray-600 hover:bg-green-100 hover:text-green-700 transition" data-topic="<?php echo htmlspecialchars($t['name']); ?>">
                                    #<?php echo htmlspecialchars($t['name']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" name="topic" id="topicInput" placeholder="选择话题或输入新话题" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                    </div>

                    <button type="button" id="submitBtn" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                        <i class="fas fa-paper-plane mr-2"></i>发布
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div id="captchaModal" class="modal">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-gray-800 mb-4"><i class="fas fa-shield-alt mr-2 text-green-600"></i>人机验证</h3>
            <p class="text-gray-600 mb-4">请输入图中的4个数字</p>
            <div class="flex items-center justify-center mb-4">
                <div id="captchaImage" class="cursor-pointer" onclick="loadCaptcha()"></div>
                <button id="refreshCaptcha" class="ml-3 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-redo text-xl"></i>
                </button>
            </div>
            <div class="mb-4">
                <input type="text" id="captchaInput" maxlength="4" placeholder="输入验证码" class="w-full px-4 py-3 border border-gray-200 rounded-xl text-center text-xl tracking-widest focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
            </div>
            <div class="flex gap-3">
                <button onclick="closeCaptcha()" class="flex-1 py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">
                    取消
                </button>
                <button id="verifyCaptcha" class="flex-1 py-3 bg-gradient-to-r from-green-500 to-blue-500 text-white rounded-xl font-medium hover:opacity-90 transition">
                    验证并发布
                </button>
            </div>
        </div>
    </div>

    <script>
        let mediaList = [];
        let uploadingCount = 0;

        document.getElementById('content').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });

        document.querySelectorAll('.topic-tag').forEach(tag => {
            tag.addEventListener('click', function() {
                document.getElementById('topicInput').value = this.dataset.topic;
            });
        });

        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');

        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            handleFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', () => handleFiles(fileInput.files));

        async function handleFiles(files) {
            if (mediaList.length >= 8) return;
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            uploadProgress.classList.remove('hidden');
            
            const filesToProcess = Array.from(files).slice(0, 8 - mediaList.length);
            let processedCount = 0;
            uploadingCount += filesToProcess.length;
            
            for (const file of filesToProcess) {
                let fileToUpload = file;
                let isVideo = file.type.startsWith('video/');
                
                const maxVideoSize = 120 * 1024 * 1024;
                if (isVideo && file.size > maxVideoSize) {
                    alert('视频大小不能超过120MB，请选择较小的视频！');
                    uploadingCount--;
                    continue;
                }
                
                const formData = new FormData();
                formData.append('file', fileToUpload);
                
                const xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const totalProgress = ((processedCount + (e.loaded / e.total)) / filesToProcess.length) * 100;
                        progressBar.style.width = totalProgress + '%';
                    }
                });
                
                await new Promise((resolve) => {
                    xhr.onload = () => {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            mediaList.push(res);
                            renderMediaPreview();
                            document.getElementById('mediaInput').value = JSON.stringify(mediaList);
                        }
                        processedCount++;
                        uploadingCount--;
                        
                        if (processedCount === filesToProcess.length) {
                            uploadProgress.classList.add('hidden');
                            progressBar.style.width = '0%';
                        }
                        resolve();
                    };
                    xhr.onerror = () => {
                        uploadingCount--;
                        resolve();
                    };
                    xhr.open('POST', 'api.php?action=upload');
                    xhr.send(formData);
                });
            }
        }

        function renderMediaPreview() {
            const container = document.getElementById('mediaPreview');
            container.innerHTML = mediaList.map((m, i) => `
                <div class="relative aspect-square rounded-xl overflow-hidden">
                    ${m.type === 'image' 
                        ? `<img src="uploads/${m.file}" class="w-full h-full object-cover">`
                        : `<video class="w-full h-full object-cover"><source src="uploads/${m.file}"></video>`
                    }
                    <button type="button" onclick="removeMedia(${i})" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-sm">×</button>
                </div>
            `).join('');
        }

        function removeMedia(index) {
            mediaList.splice(index, 1);
            renderMediaPreview();
            document.getElementById('mediaInput').value = JSON.stringify(mediaList);
        }

        document.getElementById('submitBtn').addEventListener('click', function() {
            const content = document.getElementById('content').value.trim();
            if (!content) {
                alert('请输入帖子内容');
                return;
            }
            if (uploadingCount > 0) {
                alert('文件正在上传中，请等待上传完成后再发布');
                return;
            }
            loadCaptcha();
            document.getElementById('captchaModal').classList.add('active');
        });

        function loadCaptcha() {
            fetch('api.php?action=captcha')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('captchaImage').innerHTML = `<img src="${data.image}">`;
                        document.getElementById('captchaInput').value = '';
                    }
                });
        }

        function closeCaptcha() {
            document.getElementById('captchaModal').classList.remove('active');
        }

        document.getElementById('refreshCaptcha').addEventListener('click', loadCaptcha);

        document.getElementById('verifyCaptcha').addEventListener('click', function() {
            const code = document.getElementById('captchaInput').value.trim();
            if (code.length !== 4) {
                alert('请输入4位验证码');
                return;
            }
            fetch('api.php?action=verify_captcha', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'code=' + encodeURIComponent(code)
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    document.getElementById('captchaModal').classList.remove('active');
                    document.getElementById('postForm').submit();
                } else {
                    alert('验证码错误，请重试');
                    loadCaptcha();
                }
            });
        });
    </script>
</body>
</html>
