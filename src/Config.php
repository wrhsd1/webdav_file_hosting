<?php

namespace Filebed;



class Config
{
    private static $instance = null;
    private $config = [];
    private $webdavConfigs = [];

    private function __construct()
    {
        $this->loadConfig();
        $this->loadWebDAVConfigs();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 加载配置文件
     */
    private function loadConfig()
    {
        // .env文件已经在autoload.php中加载了

        // 基本配置
        $this->config = [
            'app_name' => $_ENV['APP_NAME'] ?? 'PHP文件床',
            'app_debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'upload_max_size' => $this->parseSize($_ENV['UPLOAD_MAX_SIZE'] ?? '100M'),
            'allowed_extensions' => array_map(function($ext) { return strtolower(trim($ext)); }, explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,gif,pdf,txt,zip')),
            'default_webdav' => $_ENV['DEFAULT_WEBDAV'] ?? '',
            'api_key_required' => filter_var($_ENV['API_KEY_REQUIRED'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'api_key' => $_ENV['API_KEY'] ?? '',
            'rate_limit_enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'rate_limit_max_requests' => (int)($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 100),
            'rate_limit_time_window' => (int)($_ENV['RATE_LIMIT_TIME_WINDOW'] ?? 3600),
            'log_enabled' => filter_var($_ENV['LOG_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'log_level' => $_ENV['LOG_LEVEL'] ?? 'info',
            'log_file' => $_ENV['LOG_FILE'] ?? 'logs/app.log'
        ];
    }

    /**
     * 加载WebDAV配置
     */
    private function loadWebDAVConfigs()
    {
        $this->webdavConfigs = [];
        
        // 扫描环境变量中的WebDAV配置
        foreach ($_ENV as $key => $value) {
            if (preg_match('/^WEBDAV_([A-Z0-9_]+)_NAME$/', $key, $matches)) {
                $alias = strtolower($matches[1]);
                $prefix = "WEBDAV_{$matches[1]}_";
                
                $this->webdavConfigs[$alias] = [
                    'name' => $_ENV[$prefix . 'NAME'] ?? $alias,
                    'url' => $_ENV[$prefix . 'URL'] ?? '',
                    'username' => $_ENV[$prefix . 'USERNAME'] ?? '',
                    'password' => $_ENV[$prefix . 'PASSWORD'] ?? '',
                    'base_path' => $_ENV[$prefix . 'BASE_PATH'] ?? '/'
                ];
            }
        }
    }

    /**
     * 获取配置值
     */
    public function get($key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 获取所有WebDAV配置
     */
    public function getWebDAVConfigs()
    {
        return $this->webdavConfigs;
    }

    /**
     * 获取指定WebDAV配置
     */
    public function getWebDAVConfig($alias)
    {
        return $this->webdavConfigs[$alias] ?? null;
    }

    /**
     * 获取默认WebDAV配置
     */
    public function getDefaultWebDAVConfig()
    {
        $defaultAlias = $this->get('default_webdav');
        if ($defaultAlias && isset($this->webdavConfigs[$defaultAlias])) {
            return $this->webdavConfigs[$defaultAlias];
        }
        
        // 如果没有默认配置，返回第一个可用的配置
        return !empty($this->webdavConfigs) ? reset($this->webdavConfigs) : null;
    }

    /**
     * 检查文件扩展名是否允许
     */
    public function isAllowedExtension($filename)
    {
        if (empty($filename)) {
            return false;
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = $this->get('allowed_extensions', []);

        return in_array($extension, $allowedExtensions);
    }

    /**
     * 检查文件大小是否超限
     */
    public function isFileSizeAllowed($fileSize)
    {
        $maxSize = $this->get('upload_max_size', 0);
        return $maxSize === 0 || $fileSize <= $maxSize;
    }

    /**
     * 验证API密钥
     */
    public function validateApiKey($providedKey)
    {
        if (!$this->get('api_key_required', false)) {
            return true;
        }
        
        $configuredKey = $this->get('api_key', '');
        return !empty($configuredKey) && hash_equals($configuredKey, $providedKey);
    }

    /**
     * 解析文件大小字符串（如 "100M", "1G"）
     */
    private function parseSize($sizeStr)
    {
        $sizeStr = trim($sizeStr);
        $unit = strtoupper(substr($sizeStr, -1));
        $size = (float)substr($sizeStr, 0, -1);
        
        switch ($unit) {
            case 'G':
                return $size * 1024 * 1024 * 1024;
            case 'M':
                return $size * 1024 * 1024;
            case 'K':
                return $size * 1024;
            default:
                return (float)$sizeStr;
        }
    }

    /**
     * 格式化文件大小
     */
    public function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 获取MIME类型
     */
    public function getMimeType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'avi' => 'video/x-msvideo'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * 检查是否为图片文件
     */
    public function isImageFile($filename)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return in_array($extension, $imageExtensions);
    }
}
