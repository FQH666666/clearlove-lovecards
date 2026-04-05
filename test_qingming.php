<?php
require_once 'lunar/Lunar.php';

use com\nlf\calendar\Solar;

// 测试清明节检测
function testQingming() {
    // 测试2026年清明节（4月5日）
    $solar2026 = Solar::fromYmd(2026, 4, 5);
    $festivals2026 = $solar2026->getFestivals();
    $otherFestivals2026 = $solar2026->getOtherFestivals();
    $allFestivals2026 = array_merge($festivals2026, $otherFestivals2026);
    
    echo "2026年4月5日（清明节）的节日列表：\n";
    echo implode(', ', $allFestivals2026) . "\n";
    echo "是否包含清明节：" . (in_array('清明节', $allFestivals2026) ? '是' : '否') . "\n\n";
    
    // 测试今天
    $date = new DateTime();
    $solarToday = Solar::fromYmd($date->format('Y'), $date->format('m'), $date->format('d'));
    $festivalsToday = $solarToday->getFestivals();
    $otherFestivalsToday = $solarToday->getOtherFestivals();
    $allFestivalsToday = array_merge($festivalsToday, $otherFestivalsToday);
    
    echo "今天（" . $date->format('Y-m-d') . "）的节日列表：\n";
    echo implode(', ', $allFestivalsToday) . "\n";
    echo "是否包含清明节：" . (in_array('清明节', $allFestivalsToday) ? '是' : '否') . "\n";
}

// 运行测试
testQingming();
?>