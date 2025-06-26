<?php
// XREA环境专用修复版本

// 强制设置session配置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 3600);

// 尝试设置session保存路径
$sessionPath = sys_get_temp_dir();
if (is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

// 启动session
session_start();

require_once 'vendor/autoload.php';

use Filebed\Config;
use Filebed\FileUploader;

// 重新加载环境变量
if (file_exists('.env')) {
    $envContent = file_get_contents('.env');
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$config = Config::getInstance();
$uploader = new FileUploader();

// 简单的密码保护
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';
$isAuthenticated = false;

// 检查POST登录
if (isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['login_time'] = time();
        $isAuthenticated = true;
        
        // 强制写入session
        session_write_close();
        session_start();
    }
}

// 检查session认证
if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated']) {
    // 检查session是否过期（1小时）
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < 3600) {
        $isAuthenticated = true;
    } else {
        // session过期，清除
        unset($_SESSION['admin_authenticated']);
        unset($_SESSION['login_time']);
    }
}

// 处理AJAX请求 - 使用不同的认证检查方式
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // 对于XREA环境，使用更宽松的认证检查
    $ajaxAuth = false;
    
    // 方法1：检查session
    if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated']) {
        $ajaxAuth = true;
    }
    
    // 方法2：检查cookie（备用方案）
    if (!$ajaxAuth && isset($_COOKIE[session_name()])) {
        // 重新验证session
        session_write_close();
        session_start();
        if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated']) {
            $ajaxAuth = true;
        }
    }
    
    // 方法3：临时密码验证（仅用于调试）
    if (!$ajaxAuth && isset($_POST['admin_password']) && $_POST['admin_password'] === $adminPassword) {
        $ajaxAuth = true;
    }
    
    if (!$ajaxAuth) {
        echo json_encode([
            'success' => false, 
            'error' => '未认证',
            'debug' => [
                'session_id' => session_id(),
                'session_data' => $_SESSION,
                'cookies' => $_COOKIE,
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
            ]
        ]);
        exit;
    }

    switch ($_GET['action']) {
        case 'add_webdav':
            try {
                // 获取JSON数据
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                
                // 如果JSON解析失败，尝试从POST获取
                if (!$data) {
                    $data = $_POST;
                }
                
                if (!$data || !isset($data['alias']) || !isset($data['name']) || !isset($data['url']) || !isset($data['username']) || !isset($data['password'])) {
                    throw new Exception('缺少必要参数');
                }

                $envFile = '.env';
                $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';

                $alias = strtoupper($data['alias']);
                $newConfig = "\n# WebDAV配置 - {$data['name']}\n";
                $newConfig .= "WEBDAV_{$alias}_NAME=" . $data['name'] . "\n";
                $newConfig .= "WEBDAV_{$alias}_URL=" . $data['url'] . "\n";
                $newConfig .= "WEBDAV_{$alias}_USERNAME=" . $data['username'] . "\n";
                $newConfig .= "WEBDAV_{$alias}_PASSWORD=" . $data['password'] . "\n";

                $envContent .= $newConfig;
                
                if (file_put_contents($envFile, $envContent) === false) {
                    throw new Exception('无法写入配置文件');
                }

                echo json_encode(['success' => true, 'message' => 'WebDAV配置添加成功']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'test_auth':
            echo json_encode([
                'success' => true,
                'message' => '认证成功',
                'debug' => [
                    'session_id' => session_id(),
                    'session_data' => $_SESSION,
                    'auth_method' => $ajaxAuth ? 'session' : 'unknown'
                ]
            ]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => '未知操作']);
            exit;
    }
}

// 如果未认证，显示登录页面
if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录 - XREA修复版</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card mt-5">
                        <div class="card-header">
                            <h4>管理员登录 - XREA修复版</h4>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label for="password" class="form-label">密码</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">登录</button>
                            </form>
                            
                            <hr>
                            <div class="mt-3">
                                <h6>调试信息:</h6>
                                <small>
                                    Session ID: <?= session_id() ?><br>
                                    PHP版本: <?= PHP_VERSION ?><br>
                                    服务器: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

echo "认证成功！这是XREA环境修复版本的管理页面。";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>XREA修复版管理页面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>XREA环境修复版 - WebDAV管理</h2>
        
        <div class="card mt-3">
            <div class="card-body">
                <h5>添加WebDAV配置</h5>
                <form id="addWebdavForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">别名</label>
                                <input type="text" class="form-control" name="alias" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">名称</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WebDAV URL</label>
                        <input type="url" class="form-control" name="url" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">用户名</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">密码</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addWebdav()">添加WebDAV</button>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-body">
                <h5>测试认证</h5>
                <button class="btn btn-info" onclick="testAuth()">测试AJAX认证</button>
                <div id="testResult" class="mt-2"></div>
            </div>
        </div>
    </div>

    <script>
        async function addWebdav() {
            const form = document.getElementById('addWebdavForm');
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            try {
                const response = await fetch('?action=add_webdav', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('WebDAV配置添加成功！');
                    form.reset();
                } else {
                    alert('添加失败: ' + result.error);
                    console.log('Debug info:', result.debug);
                }
            } catch (error) {
                alert('添加失败: ' + error.message);
            }
        }
        
        async function testAuth() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '测试中...';
            
            try {
                const response = await fetch('?action=test_auth');
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success">认证测试成功</div>';
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger">认证测试失败: ' + result.error + '</div>';
                }
                
                console.log('Debug info:', result.debug);
            } catch (error) {
                resultDiv.innerHTML = '<div class="alert alert-danger">请求失败: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>
