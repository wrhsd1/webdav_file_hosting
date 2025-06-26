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
        echo "文件路径不能为空";
        exit;
    }
    
    if (empty($webdavAlias)) {
        http_response_code(400);
        echo "WebDAV配置不能为空";
        exit;
    }
    
    // 获取WebDAV配置
    $webdavConfig = $config->getWebDAVConfig($webdavAlias);
    if (!$webdavConfig) {
        http_response_code(400);
        echo "WebDAV配置不存在";
        exit;
    }
    
    // 创建WebDAV客户端
    $webdavClient = new WebDAVClient(
        $webdavConfig['url'],
        $webdavConfig['username'],
        $webdavConfig['password'],
        $webdavConfig['base_path'] ?? '/'
    );
    
    // 下载文件
    $result = $webdavClient->downloadFile($filePath);
    
    if (!$result['success']) {
        http_response_code(404);
        echo "文件不存在或下载失败: " . $result['error'];
        exit;
    }
    
    // 获取文件内容和信息
    $fileContent = $result['content'];
    $contentLength = $result['size'];
    $lastModified = $result['last_modified'] ?? null;
    
    // 获取MIME类型
    $mimeType = $config->getMimeType($filePath);

    // 判断是否应该在线预览
    $previewableMimes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'text/plain', 'text/html', 'text/css', 'text/javascript', 'text/xml',
        'application/json', 'application/xml', 'application/javascript',
        'application/pdf'
    ];

    $isPreviewable = in_array($mimeType, $previewableMimes) ||
                     strpos($mimeType, 'text/') === 0 ||
                     strpos($mimeType, 'image/') === 0;

    // 检查是否强制下载
    $forceDownload = isset($_GET['download']) && $_GET['download'] === '1';

    // 设置响应头
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $contentLength);

    if ($forceDownload || !$isPreviewable) {
        // 强制下载
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    } else {
        // 在线预览
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
    }
    
    if ($lastModified) {
        header('Last-Modified: ' . $lastModified);
    }
    
    // 设置缓存头
    header('Cache-Control: public, max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    
    // 输出文件内容
    echo $fileContent;
    
} catch (Exception $e) {
    http_response_code(500);
    echo "服务器错误: " . $e->getMessage();
}
?>
