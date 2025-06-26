<?php

namespace Filebed;

class WebDAVClient
{
    private $url;
    private $username;
    private $password;
    private $basePath;
    private $timeout;

    public function __construct($url, $username, $password, $basePath = '/', $timeout = 30)
    {
        $this->url = rtrim($url, '/');
        $this->username = $username;
        $this->password = $password;
        $this->basePath = trim($basePath, '/');
        $this->timeout = $timeout;
    }

    /**
     * 上传文件到WebDAV服务器
     */
    public function uploadFile($localFilePath, $remoteFileName = null)
    {
        if (!file_exists($localFilePath)) {
            throw new \Exception("本地文件不存在: {$localFilePath}");
        }

        if ($remoteFileName === null) {
            $remoteFileName = basename($localFilePath);
        }

        // 生成唯一文件名避免冲突
        $remoteFileName = $this->generateUniqueFileName($remoteFileName);
        $remotePath = $this->basePath ? $this->basePath . '/' . $remoteFileName : $remoteFileName;

        // 正确处理URL编码，避免特殊字符问题
        $encodedPath = $this->encodeWebDAVPath($remotePath);
        $fullUrl = $this->url . '/' . ltrim($encodedPath, '/');

        // 确保目录存在
        $this->createDirectoryIfNotExists(dirname($remotePath));

        // 读取文件内容
        $fileContent = file_get_contents($localFilePath);
        if ($fileContent === false) {
            throw new \Exception("无法读取文件: {$localFilePath}");
        }

        // 尝试上传，带重试机制
        $maxRetries = 3;
        $retryCount = 0;
        $lastError = '';

        while ($retryCount < $maxRetries) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $fullUrl,
                CURLOPT_USERPWD => $this->username . ':' . $this->password,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $fileContent,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/octet-stream',
                    'Content-Length: ' . strlen($fileContent),
                    'User-Agent: PHP-WebDAV-Client/1.0'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!$error && $httpCode >= 200 && $httpCode < 300) {
                // 上传成功
                break;
            }

            // 记录错误信息
            $lastError = $error ? "cURL错误: {$error}" : "HTTP状态码: {$httpCode}, 响应: " . substr($response, 0, 200);
            $retryCount++;

            // 如果不是最后一次重试，等待一下再重试
            if ($retryCount < $maxRetries) {
                usleep(500000); // 等待0.5秒
            }
        }

        // 如果所有重试都失败了
        if ($retryCount >= $maxRetries) {
            throw new \Exception("上传失败，已重试{$maxRetries}次。最后错误: {$lastError}");
        }
        return [
            'success' => true,
            'remote_path' => $remotePath,
            'download_url' => $fullUrl,
            'file_name' => $remoteFileName,
            'file_size' => filesize($localFilePath)
        ];
    }

    /**
     * 检查文件是否存在
     */
    public function fileExists($remotePath)
    {
        $encodedPath = $this->encodeWebDAVPath($remotePath);
        $fullUrl = $this->url . '/' . ltrim($encodedPath, '/');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_CUSTOMREQUEST => 'HEAD',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_NOBODY => true
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * 创建目录（如果不存在）
     */
    private function createDirectoryIfNotExists($dirPath)
    {
        if (empty($dirPath) || $dirPath === '.') {
            return true;
        }

        $encodedPath = $this->encodeWebDAVPath($dirPath);
        $fullUrl = $this->url . '/' . ltrim($encodedPath, '/');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_CUSTOMREQUEST => 'MKCOL',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 201 = 创建成功, 405 = 目录已存在
        return in_array($httpCode, [201, 405]);
    }

    /**
     * 生成唯一文件名
     */
    private function generateUniqueFileName($originalName)
    {
        $pathInfo = pathinfo($originalName);
        $extension = isset($pathInfo['extension']) ? '.' . strtolower($pathInfo['extension']) : '';

        // 使用时间戳和随机字符串生成安全的文件名
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $microtime = substr(microtime(true) * 10000, -4); // 添加微秒精度

        // 生成完全安全的文件名，避免特殊字符问题
        $safeFileName = $timestamp . '_' . $microtime . '_' . $random . $extension;

        // 确保文件名长度不超过100字符（大多数文件系统的安全长度）
        if (strlen($safeFileName) > 100) {
            $safeFileName = $timestamp . '_' . $random . $extension;
        }

        return $safeFileName;
    }

    /**
     * 正确编码WebDAV路径
     */
    private function encodeWebDAVPath($path)
    {
        // 分割路径并分别编码每个部分
        $parts = explode('/', $path);
        $encodedParts = array_map(function($part) {
            // 使用rawurlencode而不是urlencode，符合RFC 3986标准
            return rawurlencode($part);
        }, $parts);

        return implode('/', $encodedParts);
    }

    /**
     * 删除文件
     */
    public function deleteFile($remotePath)
    {
        $encodedPath = $this->encodeWebDAVPath($remotePath);
        $fullUrl = $this->url . '/' . ltrim($encodedPath, '/');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * 下载文件
     */
    public function downloadFile($remotePath)
    {
        // 构建完整的URL，确保路径正确并进行编码
        $encodedPath = $this->encodeWebDAVPath($remotePath);
        $fullPath = '/' . ltrim($encodedPath, '/');
        $url = $this->url . $fullPath;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300, // 5分钟超时
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || !empty($error)) {
            return ['success' => false, 'error' => "下载失败: {$error}"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "下载失败，HTTP状态码: {$httpCode}"];
        }

        return [
            'success' => true,
            'content' => $content,
            'size' => strlen($content)
        ];
    }

    /**
     * 测试连接
     */
    public function testConnection()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => [
                'Depth: 0',
                'Content-Type: application/xml'
            ]
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode === 207 || $httpCode === 200 || $httpCode === 401) {
            // 401表示需要认证，但服务器是可达的
            if ($httpCode === 401) {
                return ['success' => true, 'message' => 'WebDAV服务器可达（需要认证）'];
            }
            return ['success' => true, 'message' => 'WebDAV连接成功'];
        }

        return ['success' => false, 'error' => "连接失败，HTTP状态码: {$httpCode}"];
    }
}
