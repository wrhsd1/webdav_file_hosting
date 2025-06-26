<?php

require_once 'vendor/autoload.php';

use Filebed\Config;
use Filebed\Logger;
use Filebed\FileUploader;
use Filebed\Security;

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $config = Config::getInstance();
    $logger = Logger::getInstance();
    $uploader = new FileUploader();
    $security = new Security();

    // 基本安全验证
    if (!$security->validateRequest()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => '请求被拒绝'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // API密钥验证
    if ($config->get('api_key_required', false)) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? $_GET['api_key'] ?? '';
        if (!$config->validateApiKey($apiKey)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'API密钥无效或缺失'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 简单的速率限制
    if ($config->get('rate_limit_enabled', true)) {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitFile = "temp/rate_limit_{$clientIp}.txt";
        $maxRequests = $config->get('rate_limit_max_requests', 100);
        $timeWindow = $config->get('rate_limit_time_window', 3600);
        
        if (file_exists($rateLimitFile)) {
            $data = json_decode(file_get_contents($rateLimitFile), true);
            if ($data && time() - $data['start_time'] < $timeWindow) {
                if ($data['count'] >= $maxRequests) {
                    http_response_code(429);
                    echo json_encode([
                        'success' => false,
                        'error' => '请求过于频繁，请稍后再试'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $data['count']++;
            } else {
                $data = ['start_time' => time(), 'count' => 1];
            }
        } else {
            $data = ['start_time' => time(), 'count' => 1];
        }
        
        file_put_contents($rateLimitFile, json_encode($data));
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            handleGetRequest($action, $uploader, $config);
            break;
        case 'POST':
            handlePostRequest($uploader, $logger);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => '不支持的请求方法'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '服务器内部错误: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 处理GET请求
 */
function handleGetRequest($action, $uploader, $config)
{
    switch ($action) {
        case 'webdav_list':
            // 获取WebDAV配置列表
            $configs = $uploader->getAvailableWebDAVConfigs();
            echo json_encode([
                'success' => true,
                'data' => $configs
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'test_connection':
            // 测试WebDAV连接
            $alias = $_GET['alias'] ?? '';
            if (empty($alias)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => '缺少alias参数'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $result = $uploader->testWebDAVConnection($alias);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'config':
            // 获取基本配置信息
            echo json_encode([
                'success' => true,
                'data' => [
                    'app_name' => $config->get('app_name'),
                    'upload_max_size' => $config->formatFileSize($config->get('upload_max_size')),
                    'allowed_extensions' => $config->get('allowed_extensions'),
                    'default_webdav' => $config->get('default_webdav')
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            // API使用说明
            echo json_encode([
                'success' => true,
                'message' => 'PHP WebDAV 文件床 API',
                'version' => '1.0.0',
                'endpoints' => [
                    'POST /' => '上传文件',
                    'GET /?action=webdav_list' => '获取WebDAV配置列表',
                    'GET /?action=test_connection&alias=xxx' => '测试WebDAV连接',
                    'GET /?action=config' => '获取基本配置信息'
                ],
                'upload_example' => 'curl -X POST -F "file=@example.jpg" -F "webdav=teracloud" ' . $config->get('app_url') . '/api.php'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
}

/**
 * 处理POST请求（文件上传）
 */
function handlePostRequest($uploader, $logger)
{
    // 检查是否有文件上传
    if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '没有检测到上传的文件'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $file = $_FILES['file'];
    $webdavAlias = $_POST['webdav'] ?? null;

    // 记录上传请求
    $logger->info('收到文件上传请求', [
        'file_name' => $file['name'],
        'file_size' => $file['size'],
        'webdav_alias' => $webdavAlias
    ]);

    // 执行上传
    $result = $uploader->upload($file, $webdavAlias);

    // 设置HTTP状态码
    if (!$result['success']) {
        http_response_code(400);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
