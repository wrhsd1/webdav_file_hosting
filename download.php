<?php
require_once __DIR__ . '/vendor/autoload.php';

use Filebed\Config;
use Filebed\WebDAVClient;
use Filebed\Logger;

// 设置错误处理
error_reporting(0);
ini_set('display_errors', 0);

try {
    $config = Config::getInstance();
    $logger = Logger::getInstance();
    
    // 获取文件路径参数（支持GET和POST）
    $filePath = $_GET['file'] ?? $_POST['file'] ?? '';
    $webdavAlias = $_GET['webdav'] ?? $_POST['webdav'] ?? $config->get('default_webdav');
    
    if (empty($filePath)) {
        http_response_code(400);
        echo '文件路径不能为空';
        exit;
    }
    
    // 安全检查：防止路径遍历攻击
    if (strpos($filePath, '..') !== false || strpos($filePath, '//') !== false) {
        http_response_code(403);
        echo '非法的文件路径';
        exit;
    }
    
    // 获取WebDAV配置
    $webdavConfig = $config->getWebDAVConfig($webdavAlias);
    if (!$webdavConfig) {
        http_response_code(404);
        echo 'WebDAV配置未找到';
        exit;
    }
    
    // 创建WebDAV客户端
    $client = new WebDAVClient(
        $webdavConfig['url'],
        $webdavConfig['username'],
        $webdavConfig['password'],
        $webdavConfig['base_path']
    );
    
    // 记录下载请求
    $logger->info('文件下载请求', [
        'file_path' => $filePath,
        'webdav' => $webdavAlias,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? ''
    ]);
    
    // 获取文件内容
    $result = $client->downloadFile($filePath);
    
    if (!$result['success']) {
        http_response_code(404);
        echo '文件未找到或下载失败';
        $logger->warning('文件下载失败', [
            'file_path' => $filePath,
            'error' => $result['error']
        ]);
        exit;
    }
    
    // 获取文件信息
    $fileName = basename($filePath);
    $fileContent = $result['content'];
    $fileSize = strlen($fileContent);
    
    // 检测文件MIME类型
    $mimeType = getMimeType($fileName);
    
    // 设置下载头部
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: public, max-age=3600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    
    // 支持断点续传
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if (!empty($range)) {
        handleRangeRequest($fileContent, $fileSize, $range);
    } else {
        // 输出文件内容
        echo $fileContent;
    }
    
    // 记录成功下载
    $logger->info('文件下载成功', [
        'file_path' => $filePath,
        'file_size' => $fileSize,
        'mime_type' => $mimeType
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo '服务器内部错误';
    
    if (isset($logger)) {
        $logger->error('下载处理异常', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

/**
 * 获取文件MIME类型
 */
function getMimeType($fileName)
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        // 图片
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        
        // 文档
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        
        // 文本
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        
        // 压缩文件
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        
        // 音频
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
        
        // 视频
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'webm' => 'video/webm'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

/**
 * 处理断点续传请求
 */
function handleRangeRequest($content, $fileSize, $range)
{
    // 解析Range头
    if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }
    
    $start = intval($matches[1]);
    $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
    
    // 验证范围
    if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }
    
    $length = $end - $start + 1;
    
    // 设置206部分内容响应
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . $length);
    header('Accept-Ranges: bytes');
    
    // 输出指定范围的内容
    echo substr($content, $start, $length);
}
?>
