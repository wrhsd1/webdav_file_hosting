<?php
/**
 * 文件上传测试脚本
 * 用于测试各种文件名的上传功能
 */

require_once 'vendor/autoload.php';

use Filebed\Config;
use Filebed\FileUploader;
use Filebed\WebDAVClient;
use Filebed\Security;

// 测试用例
$testCases = [
    // 正常文件名
    'normal_file.txt',
    'image.jpg',
    
    // 长文件名
    'this_is_a_very_long_filename_that_might_cause_problems_in_some_systems_and_we_need_to_test_how_our_system_handles_it_properly.pdf',
    
    // 包含特殊字符的文件名
    'file with spaces.doc',
    'file-with-dashes.txt',
    'file_with_underscores.txt',
    'file.with.dots.txt',
    'file(with)parentheses.txt',
    'file[with]brackets.txt',
    'file{with}braces.txt',
    'file@symbol.txt',
    'file#hash.txt',
    'file$dollar.txt',
    'file%percent.txt',
    'file&ampersand.txt',
    'file+plus.txt',
    'file=equals.txt',
    
    // 中文文件名
    '中文文件名.txt',
    '测试文档.pdf',
    '图片文件.jpg',
    
    // 混合字符
    'Mixed中文English123.txt',
    'file-名称_test.doc',
    
    // 边界情况
    '.hidden_file.txt',
    'file..double.dots.txt',
    'file...triple.dots.txt',
];

echo "=== 文件上传测试开始 ===\n\n";

// 创建测试文件内容
$testContent = "这是一个测试文件，用于验证文件上传功能。\nTest content for file upload validation.\n时间戳: " . date('Y-m-d H:i:s');

foreach ($testCases as $index => $originalFileName) {
    echo "测试 " . ($index + 1) . ": {$originalFileName}\n";
    
    try {
        // 创建临时测试文件
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tempFile, $testContent);
        
        // 模拟$_FILES数组
        $fileData = [
            'name' => $originalFileName,
            'tmp_name' => $tempFile,
            'size' => strlen($testContent),
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain'
        ];
        
        // 测试文件名清理
        $security = new Security();
        $sanitizedName = $security->sanitizeFileName($originalFileName);
        echo "  清理后文件名: {$sanitizedName}\n";
        
        // 测试WebDAV文件名生成
        $webdavClient = new WebDAVClient('http://test.com', 'user', 'pass', '/');
        $reflection = new ReflectionClass($webdavClient);
        $method = $reflection->getMethod('generateUniqueFileName');
        $method->setAccessible(true);
        $uniqueName = $method->invoke($webdavClient, $sanitizedName);
        echo "  生成的唯一文件名: {$uniqueName}\n";
        
        // 测试URL编码
        $encodeMethod = $reflection->getMethod('encodeWebDAVPath');
        $encodeMethod->setAccessible(true);
        $encodedPath = $encodeMethod->invoke($webdavClient, $uniqueName);
        echo "  URL编码后: {$encodedPath}\n";
        
        // 清理临时文件
        unlink($tempFile);
        
        echo "  ✅ 测试通过\n\n";
        
    } catch (Exception $e) {
        echo "  ❌ 测试失败: " . $e->getMessage() . "\n\n";
    }
}

echo "=== 文件名长度测试 ===\n";

// 测试不同长度的文件名
$lengths = [50, 100, 150, 200, 250, 300];
foreach ($lengths as $length) {
    $longName = str_repeat('a', $length - 4) . '.txt';
    echo "测试长度 {$length}: ";
    
    try {
        $security = new Security();
        $sanitized = $security->sanitizeFileName($longName);
        echo "清理后长度: " . strlen($sanitized) . " ✅\n";
    } catch (Exception $e) {
        echo "失败: " . $e->getMessage() . " ❌\n";
    }
}

echo "\n=== 特殊字符编码测试 ===\n";

$specialChars = [
    '空格 文件.txt',
    '百分号%文件.txt',
    '井号#文件.txt',
    '问号?文件.txt',
    '星号*文件.txt',
    '引号"文件.txt',
    '小于<文件.txt',
    '大于>文件.txt',
    '管道|文件.txt',
    '冒号:文件.txt',
    '分号;文件.txt'
];

foreach ($specialChars as $fileName) {
    echo "测试: {$fileName} -> ";
    
    try {
        $webdavClient = new WebDAVClient('http://test.com', 'user', 'pass', '/');
        $reflection = new ReflectionClass($webdavClient);
        $method = $reflection->getMethod('encodeWebDAVPath');
        $method->setAccessible(true);
        $encoded = $method->invoke($webdavClient, $fileName);
        echo "{$encoded} ✅\n";
    } catch (Exception $e) {
        echo "失败: " . $e->getMessage() . " ❌\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "建议:\n";
echo "1. 所有文件名都应该能够正常处理\n";
echo "2. 长文件名会被自动截断到安全长度\n";
echo "3. 特殊字符会被正确编码或替换\n";
echo "4. 生成的文件名使用时间戳+随机字符，避免冲突\n";
