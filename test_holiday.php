<?php
require_once 'lunar/Lunar.php';

use com\nlf\calendar\Solar;

// 测试清明节检测
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
    $otherFestivals = $solar->getOtherFestivals();
    $allFestivals = array_merge($festivals, $otherFestivals);
    
    echo "当前日期: " . date('Y-m-d') . "\n";
    echo "获取到的节日列表: " . implode(', ', $allFestivals) . "\n";
    
    // 检查农历节日
    foreach ($allFestivals as $festival) {
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

// 测试函数
$holiday = isHoliday();
echo "检测到的节日: " . ($holiday ? $holiday : '无') . "\n";
?>