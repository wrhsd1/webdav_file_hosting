<?php

namespace Filebed;

class FileUploader
{
    private $config;
    private $logger;
    private $security;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->security = new Security();
    }

    /**
     * 处理文件上传
     */
    public function upload($file, $webdavAlias = null)
    {
        try {
            // 验证文件
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }

            // 获取WebDAV配置
            $webdavConfig = $this->getWebDAVConfig($webdavAlias);
            if (!$webdavConfig) {
                return [
                    'success' => false,
                    'error' => 'WebDAV配置不存在或无效'
                ];
            }

            // 创建WebDAV客户端
            $webdavClient = new WebDAVClient(
                $webdavConfig['url'],
                $webdavConfig['username'],
                $webdavConfig['password'],
                $webdavConfig['base_path']
            );

            // 测试连接
            $connectionTest = $webdavClient->testConnection();
            if (!$connectionTest['success']) {
                $this->logger->error('WebDAV连接失败', [
                    'alias' => $webdavAlias,
                    'error' => $connectionTest['error']
                ]);
                return [
                    'success' => false,
                    'error' => 'WebDAV连接失败: ' . $connectionTest['error']
                ];
            }

            // 上传文件
            $uploadResult = $webdavClient->uploadFile($file['tmp_name'], $file['name']);
            
            if ($uploadResult['success']) {
                // 生成我们自己的下载链接
                $appUrl = $this->config->get('app_url');
                $downloadUrl = $appUrl . '/proxy.php?file=' . urlencode($uploadResult['remote_path']) . '&webdav=' . urlencode($webdavAlias);

                $this->logger->info('文件上传成功', [
                    'original_name' => $file['name'],
                    'file_name' => $uploadResult['file_name'],
                    'file_size' => $uploadResult['file_size'],
                    'webdav_alias' => $webdavAlias,
                    'download_url' => $downloadUrl,
                    'direct_url' => $uploadResult['download_url']
                ]);

                return [
                    'success' => true,
                    'message' => '文件上传成功',
                    'data' => [
                        'original_name' => $file['name'],
                        'file_name' => $uploadResult['file_name'],
                        'file_size' => $uploadResult['file_size'],
                        'file_size_formatted' => $this->config->formatFileSize($uploadResult['file_size']),
                        'download_url' => $downloadUrl,
                        'direct_url' => $uploadResult['download_url'],
                        'webdav_alias' => $webdavAlias,
                        'webdav_name' => $webdavConfig['name'],
                        'upload_time' => date('Y-m-d H:i:s'),
                        'is_image' => $this->config->isImageFile($file['name']),
                        'mime_type' => $this->config->getMimeType($file['name'])
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => '文件上传失败'
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('文件上传异常', [
                'error' => $e->getMessage(),
                'file' => $file['name'] ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => '上传过程中发生错误: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 验证上传的文件
     */
    private function validateFile($file)
    {
        // 检查文件是否上传成功
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => '文件大小超过了服务器限制',
                UPLOAD_ERR_FORM_SIZE => '文件大小超过了表单限制',
                UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                UPLOAD_ERR_EXTENSION => '文件上传被扩展程序阻止'
            ];

            $error = $errorMessages[$file['error']] ?? '未知上传错误';
            return ['success' => false, 'error' => $error];
        }

        // 检查文件是否真实存在
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => '文件上传验证失败'];
        }

        // 检查文件扩展名
        if (!$this->config->isAllowedExtension($file['name'])) {
            $allowedExtensions = $this->config->get('allowed_extensions', []);
            $allowedExtensionsDisplay = implode(', ', $allowedExtensions);
            return [
                'success' => false,
                'error' => "不支持的文件类型。允许的类型: {$allowedExtensionsDisplay}"
            ];
        }

        // 检查文件大小
        if (!$this->config->isFileSizeAllowed($file['size'])) {
            $maxSize = $this->config->formatFileSize($this->config->get('upload_max_size', 0));
            return [
                'success' => false,
                'error' => "文件大小超过限制。最大允许: {$maxSize}"
            ];
        }

        // 清理文件名
        $file['name'] = $this->security->sanitizeFileName($file['name']);

        // 安全检查
        $securityCheck = $this->security->validateFileSecurity($file['tmp_name'], $file['name']);
        if (!$securityCheck['valid']) {
            return ['success' => false, 'error' => $securityCheck['error']];
        }

        return ['success' => true];
    }



    /**
     * 获取WebDAV配置
     */
    private function getWebDAVConfig($alias = null)
    {
        if ($alias) {
            return $this->config->getWebDAVConfig($alias);
        }
        
        return $this->config->getDefaultWebDAVConfig();
    }

    /**
     * 获取可用的WebDAV配置列表
     */
    public function getAvailableWebDAVConfigs()
    {
        $configs = $this->config->getWebDAVConfigs();
        $result = [];
        
        foreach ($configs as $alias => $config) {
            $result[] = [
                'alias' => $alias,
                'name' => $config['name'],
                'url' => $config['url']
            ];
        }
        
        return $result;
    }

    /**
     * 测试WebDAV连接
     */
    public function testWebDAVConnection($alias)
    {
        $config = $this->config->getWebDAVConfig($alias);
        if (!$config) {
            return ['success' => false, 'error' => 'WebDAV配置不存在'];
        }

        $client = new WebDAVClient(
            $config['url'],
            $config['username'],
            $config['password'],
            $config['base_path']
        );

        return $client->testConnection();
    }
}
