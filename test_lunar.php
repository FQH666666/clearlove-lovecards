<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'lunar/Lunar.php';

use com\nlf\calendar\Solar;

// 测试清明节检测
$date = new DateTime('2026-04-05'); // 2026年清明节
$solar = Solar::fromDate($date);

// 获取节日列表
$festivals = $solar->getFestivals();
echo "2026-04-05 节日列表：\n";
print_r($festivals);

echo "\n是否包含清明节：" . (in_array('清明节', $festivals) ? '是' : '否') . "\n";

// 测试其他日期
$date2 = new DateTime('2026-01-01'); // 元旦
$solar2 = Solar::fromDate($date2);
$festivals2 = $solar2->getFestivals();
echo "\n2026-01-01 节日列表：\n";
print_r($festivals2);

// 测试今天
$today = new DateTime();
echo "\n今天日期：" . $today->format('Y-m-d') . "\n";
$solarToday = Solar::fromDate($today);
$festivalsToday = $solarToday->getFestivals();
echo "今天节日列表：\n";
print_r($festivalsToday);
?>