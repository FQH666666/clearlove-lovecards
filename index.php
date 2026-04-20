<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'Parsedown.php';
require_once 'lunar/Lunar.php';

use com\nlf\calendar\Solar;

$db = getDB();
if (!$db) {
    header('Location: install.php');
    exit;
}

$isFirstVisit = isFirstVisitToday($db);
recordVisit($db);

$search = trim($_GET['search'] ?? '');
if ($search) {
    $searchTerm = $db->quote('%' . $search . '%');
    try {
        $stmt = $db->query("SELECT p.*, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count 
                              FROM posts p WHERE p.content LIKE $searchTerm OR p.topic LIKE $searchTerm ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 50");
    } catch (PDOException $e) {
        $stmt = $db->query("SELECT p.*, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count 
                              FROM posts p WHERE p.content LIKE $searchTerm OR p.topic LIKE $searchTerm ORDER BY p.created_at DESC LIMIT 50");
    }
    $posts = $stmt->fetchAll();
} else {
    $posts = getPosts($db);
}
$announcement = getActiveAnnouncement($db);
$parsedown = new Parsedown();
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.staticfile.net/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.staticfile.net/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* 背景音乐CD图标 */
        .music-cd {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #f5f5f5, #e0e0e0);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .music-cd:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }
        
        .music-cd.playing {
            animation: spin 20s linear infinite;
        }
        
        .music-cd::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #333;
            z-index: 1;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
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
            'default' => ['colors' => '#66bb6a,#42a5f5', 'bg' => '#e8f5e9,#e3f2fd', 'card' => '165,214,167', 'shimmer' => '#e8f5e9,#c8e6c9', 'tag' => '#a5d6a7,#90caf9', 'tagText' => '#2e7d32'],
            'pink' => ['colors' => '#ec407a,#ff80ab', 'bg' => '#fce4ec,#f8bbd0', 'card' => '236,64,122', 'shimmer' => '#fce4ec,#f8bbd0', 'tag' => '#f48fb1,#f06292', 'tagText' => '#c2185b'],
            'purple' => ['colors' => '#7e57c2,#ab47bc', 'bg' => '#ede7f6,#e1bee7', 'card' => '126,87,194', 'shimmer' => '#ede7f6,#e1bee7', 'tag' => '#ce93d8,#ba68c8', 'tagText' => '#5e35b1'],
            'orange' => ['colors' => '#ffa726,#ff7043', 'bg' => '#fff3e0,#ffe0b2', 'card' => '255,167,38', 'shimmer' => '#fff3e0,#ffe0b2', 'tag' => '#ffcc80,#ffab40', 'tagText' => '#ef6c00'],
            'red' => ['colors' => '#ef5350,#e53935', 'bg' => '#ffebee,#ffcdd2', 'card' => '239,83,80', 'shimmer' => '#ffebee,#ffcdd2', 'tag' => '#ef9a9a,#e57373', 'tagText' => '#c62828'],
            'teal' => ['colors' => '#26c6da,#00acc1', 'bg' => '#e0f7fa,#b2ebf2', 'card' => '38,198,218', 'shimmer' => '#e0f7fa,#b2ebf2', 'tag' => '#80deea,#4dd0e1', 'tagText' => '#00695c'],
            'amber' => ['colors' => '#ffca28,#ff9800', 'bg' => '#fff8e1,#ffecb3', 'card' => '255,202,40', 'shimmer' => '#fff8e1,#ffecb3', 'tag' => '#ffd54f,#ffb74d', 'tagText' => '#ef6c00'],
            'indigo' => ['colors' => '#3f51b5,#5c6bc0', 'bg' => '#e8eaf6,#c5cae9', 'card' => '63,81,181', 'shimmer' => '#e8eaf6,#c5cae9', 'tag' => '#9fa8da,#7986cb', 'tagText' => '#1a237e'],
            'deep-purple' => ['colors' => '#5e35b1,#4527a0', 'bg' => '#ede7f6,#d1c4e9', 'card' => '94,53,177', 'shimmer' => '#ede7f6,#d1c4e9', 'tag' => '#b39ddb,#9575cd', 'tagText' => '#311b92'],
            'light-blue' => ['colors' => '#29b6f6,#03a9f4', 'bg' => '#e3f2fd,#bbdefb', 'card' => '41,182,246', 'shimmer' => '#e3f2fd,#bbdefb', 'tag' => '#81d4fa,#4fc3f7', 'tagText' => '#01579b'],
            'lime' => ['colors' => '#cddc39,#8bc34a', 'bg' => '#f1f8e9,#dcedc8', 'card' => '205,220,57', 'shimmer' => '#f1f8e9,#dcedc8', 'tag' => '#dce775,#c5e1a5', 'tagText' => '#827717'],
            'cyan' => ['colors' => '#00bcd4,#0097a7', 'bg' => '#e0f7fa,#b2ebf2', 'card' => '0,188,212', 'shimmer' => '#e0f7fa,#b2ebf2', 'tag' => '#80deea,#4dd0e1', 'tagText' => '#006064'],
            'teal-green' => ['colors' => '#66bb6a,#43a047', 'bg' => '#e8f5e9,#c8e6c9', 'card' => '102,187,106', 'shimmer' => '#e8f5e9,#c8e6c9', 'tag' => '#a5d6a7,#81c784', 'tagText' => '#2e7d32'],
            'purple-pink' => ['colors' => '#9c27b0,#e91e63', 'bg' => '#f3e5f5,#fce4ec', 'card' => '156,39,176', 'shimmer' => '#f3e5f5,#fce4ec', 'tag' => '#ce93d8,#f48fb1', 'tagText' => '#4a148c'],
            'blue-indigo' => ['colors' => '#1e88e5,#3949ab', 'bg' => '#e3f2fd,#e8eaf6', 'card' => '30,136,229', 'shimmer' => '#e3f2fd,#e8eaf6', 'tag' => '#64b5f6,#9fa8da', 'tagText' => '#0d47a1'],
            'orange-red' => ['colors' => '#ff9800,#f44336', 'bg' => '#fff3e0,#ffebee', 'card' => '255,152,0', 'shimmer' => '#fff3e0,#ffebee', 'tag' => '#ffcc80,#ef9a9a', 'tagText' => '#ef6c00'],
            'green-teal' => ['colors' => '#4caf50,#009688', 'bg' => '#e8f5e9,#e0f2f1', 'card' => '76,175,80', 'shimmer' => '#e8f5e9,#e0f2f1', 'tag' => '#a5d6a7,#80cbc4', 'tagText' => '#1b5e20'],
            'blue-cyan' => ['colors' => '#2196f3,#00bcd4', 'bg' => '#e3f2fd,#e0f7fa', 'card' => '33,150,243', 'shimmer' => '#e3f2fd,#e0f7fa', 'tag' => '#64b5f6,#80deea', 'tagText' => '#0d47a1'],
            'purple-indigo' => ['colors' => '#7b1fa2,#303f9f', 'bg' => '#f3e5f5,#e8eaf6', 'card' => '123,31,162', 'shimmer' => '#f3e5f5,#e8eaf6', 'tag' => '#ba68c8,#9fa8da', 'tagText' => '#311b92'],
            'pink-red' => ['colors' => '#e91e63,#c2185b', 'bg' => '#fce4ec,#ffebee', 'card' => '233,30,99', 'shimmer' => '#fce4ec,#ffebee', 'tag' => '#f48fb1,#ef9a9a', 'tagText' => '#880e4f'],
            'amber-orange' => ['colors' => '#ffb300,#ff7043', 'bg' => '#fff8e1,#fff3e0', 'card' => '255,179,0', 'shimmer' => '#fff8e1,#fff3e0', 'tag' => '#ffd54f,#ffcc80', 'tagText' => '#ef6c00'],
            'teal-cyan' => ['colors' => '#26a69a,#00acc1', 'bg' => '#e0f2f1,#e0f7fa', 'card' => '38,166,154', 'shimmer' => '#e0f2f1,#e0f7fa', 'tag' => '#80cbc4,#80deea', 'tagText' => '#00695c'],
            'indigo-purple' => ['colors' => '#536dfe,#7b1fa2', 'bg' => '#ede7f6,#f3e5f5', 'card' => '83,109,254', 'shimmer' => '#ede7f6,#f3e5f5', 'tag' => '#9fa8da,#ba68c8', 'tagText' => '#1a237e'],
            'green-lime' => ['colors' => '#4caf50,#cddc39', 'bg' => '#e8f5e9,#f1f8e9', 'card' => '76,175,80', 'shimmer' => '#e8f5e9,#f1f8e9', 'tag' => '#a5d6a7,#dce775', 'tagText' => '#1b5e20'],
            'christmas' => ['colors' => '#e53935,#43a047', 'bg' => '#ffebee,#e8f5e9', 'card' => '229,57,53', 'shimmer' => '#ffebee,#e8f5e9', 'tag' => '#ef9a9a,#a5d6a7', 'tagText' => '#c62828', 'bgEffect' => 'christmas'],
            'valentine' => ['colors' => '#e91e63,#c2185b', 'bg' => '#fce4ec,#f48fb1', 'card' => '233,30,99', 'shimmer' => '#fce4ec,#f48fb1', 'tag' => '#f48fb1,#e91e63', 'tagText' => '#880e4f', 'bgEffect' => 'valentine'],
            'spring' => ['colors' => '#e53935,#ffb300', 'bg' => '#ffebee,#fff8e1', 'card' => '229,57,53', 'shimmer' => '#ffebee,#fff8e1', 'tag' => '#ef9a9a,#ffd54f', 'tagText' => '#c62828', 'bgEffect' => 'spring'],
            'mayday' => ['colors' => '#4caf50,#81c784', 'bg' => '#e8f5e9,#c8e6c9', 'card' => '76,175,80', 'shimmer' => '#e8f5e9,#c8e6c9', 'tag' => '#a5d6a7,#81c784', 'tagText' => '#2e7d32', 'bgEffect' => 'mayday'],
            'children' => ['colors' => '#ff9800,#ffb74d', 'bg' => '#fff3e0,#ffe0b2', 'card' => '255,152,0', 'shimmer' => '#fff3e0,#ffe0b2', 'tag' => '#ffcc80,#ffb74d', 'tagText' => '#ef6c00', 'bgEffect' => 'children'],
            'midautumn' => ['colors' => '#9c27b0,#ba68c8', 'bg' => '#f3e5f5,#e1bee7', 'card' => '156,39,176', 'shimmer' => '#f3e5f5,#e1bee7', 'tag' => '#ce93d8,#ba68c8', 'tagText' => '#4a148c', 'bgEffect' => 'midautumn'],
            'national' => ['colors' => '#e53935,#ff5722', 'bg' => '#ffebee,#fbe9e7', 'card' => '229,57,53', 'shimmer' => '#ffebee,#fbe9e7', 'tag' => '#ef9a9a,#ffab91', 'tagText' => '#c62828', 'bgEffect' => 'national'],
            'qingming' => ['colors' => '#4caf50,#81c784', 'bg' => '#e8f5e9,#c8e6c9', 'card' => '76,175,80', 'shimmer' => '#e8f5e9,#c8e6c9', 'tag' => '#a5d6a7,#81c784', 'tagText' => '#2e7d32', 'bgEffect' => 'qingming'],
            'dragon' => ['colors' => '#e53935,#ff9800', 'bg' => '#ffebee,#fff3e0', 'card' => '229,57,53', 'shimmer' => '#ffebee,#fff3e0', 'tag' => '#ef9a9a,#ffcc80', 'tagText' => '#c62828', 'bgEffect' => 'dragon'],
            'qixi' => ['colors' => '#e91e63,#9c27b0', 'bg' => '#fce4ec,#f3e5f5', 'card' => '233,30,99', 'shimmer' => '#fce4ec,#f3e5f5', 'tag' => '#f48fb1,#ce93d8', 'tagText' => '#880e4f', 'bgEffect' => 'qixi']
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
            $cardColor = hexToRgb($primaryColor);
            $shimmerColors = adjustBrightness($primaryColor, 30) . ',' . adjustBrightness($primaryColor, 15);
            $tagColors = adjustBrightness($primaryColor, 20) . ',' . adjustBrightness($secondaryColor, 20);
            $tagTextColor = darkenColor($primaryColor, 40);
        } else {
            $themeConfig = $themes[$theme] ?? $themes['default'];
            $primaryColor = explode(',', $themeConfig['colors'])[0];
            $secondaryColor = explode(',', $themeConfig['colors'])[1];
            $bgColors = $themeConfig['bg'];
            $cardColor = $themeConfig['card'];
            $shimmerColors = $themeConfig['shimmer'];
            $tagColors = $themeConfig['tag'];
            $tagTextColor = $themeConfig['tagText'];
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
        
        function darkenColor($hex, $percent) {
            $hex = str_replace('#', '', $hex);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            $r = floor($r * (100 - $percent) / 100);
            $g = floor($g * (100 - $percent) / 100);
            $b = floor($b * (100 - $percent) / 100);
            
            return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        }
        ?>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, <?php echo $bgColors; ?>);
            min-height: 100vh;
        }
        .post-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(<?php echo $cardColor; ?>, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
            cursor: pointer;
        }
        .post-card:hover {
            transform: translateY(-8px) rotateX(2deg);
            box-shadow: 0 20px 40px rgba(<?php echo $cardColor; ?>, 0.25);
        }
        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        .media-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .media-item {
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
        }
        .media-item img, .media-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .lazy-image {
            background: linear-gradient(90deg, <?php echo $shimmerColors; ?>);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .fab-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);
            box-shadow: 0 6px 20px rgba(<?php echo $cardColor; ?>, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .fab-btn:hover {
            transform: scale(1.15) rotate(90deg);
            box-shadow: 0 8px 25px rgba(<?php echo $cardColor; ?>, 0.5);
        }
        .comment-item {
            background: rgba(<?php echo $cardColor; ?>, 0.1);
            border-radius: 12px;
        }
        .tag {
            background: linear-gradient(135deg, <?php echo $tagColors; ?>);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: <?php echo $tagTextColor; ?>;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
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
                radial-gradient(circle at 20% 30%, rgba(76, 175, 80, 0.3) 0%, transparent 20%),
                radial-gradient(circle at 80% 30%, rgba(76, 175, 80, 0.3) 0%, transparent 20%),
                radial-gradient(circle at 50% 70%, rgba(76, 175, 80, 0.3) 0%, transparent 20%);
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
        
        /* 清明节背景效果 */
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
        
        /* 端午节背景效果 */
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
        
        /* 七夕节背景效果 */
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
<body class="pb-20<?php if ($holiday) echo ' ' . $holiday; ?>">
    <!-- 背景音乐 -->
    <?php if (defined('BG_MUSIC_ENABLED') && BG_MUSIC_ENABLED && defined('BG_MUSIC_FILE') && BG_MUSIC_FILE): ?>
    <audio id="bgMusic" loop>
        <source src="uploads/<?php echo BG_MUSIC_FILE; ?>" type="audio/mp3">
    </audio>
    <div class="music-cd" id="musicCd" onclick="toggleMusic()">
        <i class="fas fa-music text-2xl text-gray-600 z-10"></i>
    </div>
    <?php endif; ?>
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-md shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h1 class="text-2xl font-bold bg-gradient-to-r from-green-600 to-blue-500 bg-clip-text text-transparent">
                    <i class="fas fa-heart mr-2"></i><?php echo SITE_NAME; ?>
                </h1>
                <form action="index.php" method="GET" class="w-full md:w-64">
                    <div class="relative">
                        <input type="text" name="search" placeholder="搜索表白内容..." value="<?php echo htmlspecialchars($search); ?>" class="w-full px-4 py-2 pl-10 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <?php if ($posts): ?>
            <div class="space-y-6">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card rounded-2xl p-6" data-post-id="<?php echo $post['id']; ?>" onclick="goToDetail(<?php echo $post['id']; ?>)">
                        <?php if (!empty($post['is_pinned'])): ?>
                            <div class="mb-4 flex items-center gap-2 text-amber-600">
                                <i class="fas fa-thumbtack text-lg"></i>
                                <span class="text-sm font-semibold">置顶</span>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center mb-4">
                            <?php $avatarColor = getRandomColor($post['id']); ?>
                            <div class="avatar mr-3" style="background: <?php echo $avatarColor; ?>;">
                                <?php echo mb_substr($post['nickname'], 0, 1); ?>
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($post['nickname']); ?>
                                </div>
                                <div class="text-sm text-gray-500"><?php echo formatTime($post['created_at']); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($post['topic']): ?>
                            <span class="tag mb-3 inline-block"><i class="fas fa-hashtag mr-1"></i><?php echo htmlspecialchars($post['topic']); ?></span>
                        <?php endif; ?>
                        
                        <div class="text-gray-700 mb-4">
                            <?php 
                            $content = htmlspecialchars($post['content']);
                            if (mb_strlen($content) > 300) {
                                echo mb_substr($content, 0, 300) . '...';
                                echo '<div class="mt-2"><span class="text-green-600 hover:text-green-700 font-medium">点击查看全文 <i class="fas fa-arrow-right ml-1"></i></span></div>';
                            } else {
                                echo $content;
                            }
                            ?>
                        </div>
                        
                        <?php if ($post['media']): ?>
                            <div class="media-grid mb-4">
                                <?php 
                                $mediaList = json_decode($post['media'], true);
                                foreach ($mediaList as $media): 
                                ?>
                                    <div class="media-item" onclick="event.stopPropagation(); openMedia(<?php echo htmlspecialchars(json_encode($media)); ?>)">
                                        <?php if ($media['type'] === 'image'): ?>
                                            <img class="lazy-image" data-src="uploads/<?php echo $media['file']; ?>" alt="图片">
                                        <?php else: ?>
                                            <video poster="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect fill='%23a5d6a7' width='100' height='100'/%3E%3Ctext x='50' y='55' text-anchor='middle' fill='white' font-size='40'%3E%E2%96%B6%3C/text%3E%3C/svg%3E">
                                                <source src="uploads/<?php echo $media['file']; ?>" type="video/mp4">
                                            </video>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <i class="fas fa-play-circle text-white text-4xl drop-shadow-lg"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center gap-6 mb-4">
                            <button class="like-btn flex items-center gap-2 text-gray-600 hover:text-pink-500 transition-colors" data-post-id="<?php echo $post['id']; ?>" onclick="event.stopPropagation();">
                                <i class="fas fa-heart text-xl"></i>
                                <span class="like-count"><?php echo $post['likes']; ?></span>
                            </button>
                            <div class="flex items-center gap-2 text-gray-600 hover:text-blue-500 transition-colors" onclick="event.stopPropagation(); goToDetail(<?php echo $post['id']; ?>);">
                                <i class="fas fa-comment text-xl"></i>
                                <span><?php echo $post['comment_count']; ?></span>
                            </div>
                        </div>
                        
                        <?php 
                        $comments = getComments($db, $post['id'], 4);
                        if ($comments): 
                        ?>
                            <div class="border-t border-gray-100 pt-4" onclick="event.stopPropagation(); goToDetail(<?php echo $post['id']; ?>);">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment-item p-3 mb-2">
                                        <div class="font-medium text-gray-700 text-sm"><?php echo htmlspecialchars($comment['nickname']); ?></div>
                                        <div class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($comment['content']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($post['comment_count'] > 4): ?>
                                    <span class="text-green-600 hover:text-green-700 text-sm font-medium">
                                        点击查看所有评论 <i class="fas fa-chevron-down ml-1"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20">
                <i class="fas fa-heart text-6xl text-green-300 mb-4"></i>
                <p class="text-gray-500 text-lg">还没有帖子，快来发布第一条吧！</p>
            </div>
        <?php endif; ?>
    </main>

    <a href="post.php" class="fab-btn flex items-center justify-center text-white text-2xl">
        <i class="fas fa-plus"></i>
    </a>

    <div id="mediaModal" class="modal">
        <div class="modal-content p-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">媒体预览</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <div id="mediaContent"></div>
        </div>
    </div>

    <script>
        function lazyLoad() {
            const images = document.querySelectorAll('.lazy-image[data-src]');
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy-image');
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            });
            images.forEach(img => imageObserver.observe(img));
        }
        lazyLoad();

        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const countSpan = this.querySelector('.like-count');
                fetch('api.php?action=like', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'post_id=' + postId
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        countSpan.textContent = data.likes;
                        this.querySelector('i').classList.add('text-pink-500');
                    }
                });
            });
        });

        function openMedia(media) {
            const modal = document.getElementById('mediaModal');
            const content = document.getElementById('mediaContent');
            if (media.type === 'image') {
                content.innerHTML = '<img src="uploads/' + media.file + '" class="max-w-full max-h-[70vh] rounded-lg">';
            } else {
                content.innerHTML = '<video controls class="max-w-full max-h-[70vh] rounded-lg"><source src="uploads/' + media.file + '" type="video/mp4"></video>';
            }
            modal.classList.add('active');
        }

        function goToDetail(postId) {
            window.location.href = 'detail.php?id=' + postId;
        }

        function closeModal() {
            document.getElementById('mediaModal').classList.remove('active');
        }

        document.getElementById('mediaModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>

    <?php if ($announcement && $isFirstVisit && !isset($_COOKIE['announcement_seen_' . $announcement['id']])): ?>
    <div id="announcementModal" class="modal active">
        <div class="modal-content p-6 max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-bullhorn text-yellow-500 mr-2"></i>公告</h3>
                <button onclick="closeAnnouncement()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <div class="text-gray-700 mb-4 prose max-w-none"><?php echo $parsedown->text($announcement['content']); ?></div>
            <?php if ($announcement['image']): ?>
                <img src="uploads/<?php echo $announcement['image']; ?>" class="w-full rounded-lg mb-4">
            <?php endif; ?>
            <button onclick="closeAnnouncement()" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                我知道了
            </button>
        </div>
    </div>
    <script>
        function closeAnnouncement() {
            document.getElementById('announcementModal').classList.remove('active');
            document.cookie = 'announcement_seen_<?php echo $announcement["id"]; ?>=1; expires=' + new Date(Date.now() + 86400000).toUTCString() + '; path=/';
        }
    </script>
    <?php endif; ?>
    
    <!-- 背景音乐控制 -->
    <?php if (defined('BG_MUSIC_ENABLED') && BG_MUSIC_ENABLED && defined('BG_MUSIC_FILE') && BG_MUSIC_FILE): ?>
    <script>
        const bgMusic = document.getElementById('bgMusic');
        const musicCd = document.getElementById('musicCd');
        const volume = <?php echo defined('BG_MUSIC_VOLUME') ? BG_MUSIC_VOLUME : 50; ?> / 100;
        
        // 设置音量
        bgMusic.volume = volume;
        
        // 自动播放音乐
        window.addEventListener('DOMContentLoaded', function() {
            // 尝试自动播放
            bgMusic.play().catch(function(error) {
                console.log('自动播放失败:', error);
                // 自动播放失败时，等待用户交互后再播放
                document.addEventListener('click', function playOnClick() {
                    bgMusic.play().catch(function() {});
                    document.removeEventListener('click', playOnClick);
                }, { once: true });
            });
            
            // 添加播放状态类
            if (!bgMusic.paused) {
                musicCd.classList.add('playing');
            }
        });
        
        // 确保音乐默认播放
        function ensureMusicPlaying() {
            if (bgMusic.paused) {
                bgMusic.play().catch(function() {});
            }
        }
        
        // 页面加载完成后确保音乐播放
        window.addEventListener('load', ensureMusicPlaying);
        
        // 当用户与页面交互时确保音乐播放
        document.addEventListener('click', ensureMusicPlaying, { once: true });
        document.addEventListener('touchstart', ensureMusicPlaying, { once: true });
        
        function toggleMusic() {
            if (bgMusic.paused) {
                bgMusic.play().catch(function(error) {
                    console.log('播放失败:', error);
                });
                musicCd.classList.add('playing');
            } else {
                bgMusic.pause();
                musicCd.classList.remove('playing');
            }
        }
        
        // 监听播放状态变化
        bgMusic.addEventListener('play', function() {
            musicCd.classList.add('playing');
        });
        
        bgMusic.addEventListener('pause', function() {
            musicCd.classList.remove('playing');
        });
    </script>
    <?php endif; ?>
</body>
</html>