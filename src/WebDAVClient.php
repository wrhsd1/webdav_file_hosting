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
        $fullUrl = $this->url . '/' . ltrim($remotePath, '/');

        // 确保目录存在
        $this->createDirectoryIfNotExists(dirname($remotePath));

        // 读取文件内容
        $fileContent = file_get_contents($localFilePath);
        if ($fileContent === false) {
            throw new \Exception("无法读取文件: {$localFilePath}");
        }

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
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Length: ' . strlen($fileContent)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL错误: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception("上传失败，HTTP状态码: {$httpCode}, 响应: {$response}");
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
        $fullUrl = $this->url . '/' . ltrim($remotePath, '/');
        
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

        $fullUrl = $this->url . '/' . ltrim($dirPath, '/');
        
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
        $name = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        // 添加时间戳和随机字符串
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid()), 0, 6);
        
        return $name . '_' . $timestamp . '_' . $random . $extension;
    }

    /**
     * 删除文件
     */
    public function deleteFile($remotePath)
    {
        $fullUrl = $this->url . '/' . ltrim($remotePath, '/');
        
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
        // 构建完整的URL，确保路径正确
        $fullPath = '/' . ltrim($remotePath, '/');
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
