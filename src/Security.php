<?php

namespace Filebed;

class Security
{
    private $config;
    private $logger;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * 验证请求来源
     */
    public function validateRequest()
    {
        // 检查请求方法
        $allowedMethods = ['GET', 'POST', 'OPTIONS'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            $this->logger->warning('不允许的请求方法', [
                'method' => $_SERVER['REQUEST_METHOD'],
                'ip' => $this->getClientIp()
            ]);
            return false;
        }

        // 检查User-Agent（基本的机器人过滤）
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent) || $this->isSuspiciousUserAgent($userAgent)) {
            $this->logger->warning('可疑的User-Agent', [
                'user_agent' => $userAgent,
                'ip' => $this->getClientIp()
            ]);
            return false;
        }

        return true;
    }

    /**
     * 检查是否为可疑的User-Agent
     */
    private function isSuspiciousUserAgent($userAgent)
    {
        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/wget/i',
            '/curl/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 验证文件安全性
     */
    public function validateFileSecurity($filePath, $fileName)
    {
        // 检查文件是否存在
        if (!file_exists($filePath)) {
            return ['valid' => false, 'error' => '文件不存在'];
        }

        // 检查文件大小
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize === 0) {
            return ['valid' => false, 'error' => '文件大小无效'];
        }

        // 检查文件扩展名
        if (!$this->config->isAllowedExtension($fileName)) {
            return ['valid' => false, 'error' => '不允许的文件类型'];
        }

        // 检查文件内容
        $contentCheck = $this->checkFileContent($filePath, $fileName);
        if (!$contentCheck['valid']) {
            return $contentCheck;
        }

        // 检查文件头部
        $headerCheck = $this->checkFileHeader($filePath, $fileName);
        if (!$headerCheck['valid']) {
            return $headerCheck;
        }

        return ['valid' => true];
    }

    /**
     * 检查文件内容
     */
    private function checkFileContent($filePath, $fileName)
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 对于图片文件，验证是否为真实的图片
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                return ['valid' => false, 'error' => '无效的图片文件'];
            }
        }

        // 检查文件内容是否包含恶意代码
        $content = file_get_contents($filePath, false, null, 0, 8192); // 只读取前8KB
        
        $maliciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/base64_decode\s*\(/i'
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logger->warning('检测到恶意文件内容', [
                    'file_name' => $fileName,
                    'pattern' => $pattern,
                    'ip' => $this->getClientIp()
                ]);
                return ['valid' => false, 'error' => '文件内容包含不安全的代码'];
            }
        }

        return ['valid' => true];
    }

    /**
     * 检查文件头部
     */
    private function checkFileHeader($filePath, $fileName)
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 定义文件头部签名
        $signatures = [
            'jpg' => ["\xFF\xD8\xFF"],
            'jpeg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
            'gif' => ["GIF87a", "GIF89a"],
            'pdf' => ["%PDF"],
            'zip' => ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"]
        ];

        if (isset($signatures[$extension])) {
            $fileHeader = file_get_contents($filePath, false, null, 0, 16);
            $validHeader = false;
            
            foreach ($signatures[$extension] as $signature) {
                if (strpos($fileHeader, $signature) === 0) {
                    $validHeader = true;
                    break;
                }
            }
            
            if (!$validHeader) {
                return ['valid' => false, 'error' => '文件头部与扩展名不匹配'];
            }
        }

        return ['valid' => true];
    }

    /**
     * 清理文件名
     */
    public function sanitizeFileName($fileName)
    {
        // 移除路径分隔符和特殊字符
        $fileName = basename($fileName);
        $fileName = preg_replace('/[^a-zA-Z0-9\._\-\x{4e00}-\x{9fa5}]/u', '_', $fileName);
        
        // 限制文件名长度
        if (strlen($fileName) > 255) {
            $pathInfo = pathinfo($fileName);
            $name = substr($pathInfo['filename'], 0, 200);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $fileName = $name . $extension;
        }
        
        return $fileName;
    }

    /**
     * 获取客户端IP
     */
    public function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * 生成CSRF令牌
     */
    public function generateCsrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }

    /**
     * 验证CSRF令牌
     */
    public function validateCsrfToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
