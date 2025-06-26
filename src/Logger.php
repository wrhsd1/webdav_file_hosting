<?php

namespace Filebed;

class Logger
{
    private static $instance = null;
    private $config;
    private $logFile;

    private function __construct()
    {
        $this->config = Config::getInstance();
        $this->logFile = $this->config->get('log_file', 'logs/app.log');
        
        // 确保日志目录存在
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 记录日志
     */
    public function log($level, $message, $context = [])
    {
        if (!$this->config->get('log_enabled', true)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'ip' => $ip,
            'message' => $message,
            'context' => $context,
            'user_agent' => $userAgent
        ];

        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public function info($message, $context = [])
    {
        $this->log('info', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('warning', $message, $context);
    }

    public function debug($message, $context = [])
    {
        $this->log('debug', $message, $context);
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp()
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
}
