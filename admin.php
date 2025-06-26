<?php
session_start();
require_once 'vendor/autoload.php';

use Filebed\Config;
use Filebed\FileUploader;

$config = Config::getInstance();
$uploader = new FileUploader();

// 简单的密码保护
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';
$isAuthenticated = false;

if (isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $isAuthenticated = true;
    }
} elseif (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated']) {
    $isAuthenticated = true;
}

if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card mt-5">
                        <div class="card-header">
                            <h4>管理员登录</h4>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label for="password" class="form-label">密码</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">登录</button>
                            </form>
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

// 处理AJAX请求
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // 检查认证状态
    if (!$isAuthenticated) {
        echo json_encode(['success' => false, 'error' => '未认证']);
        exit;
    }

    switch ($_GET['action']) {
        case 'test_webdav':
            $alias = $_GET['alias'] ?? '';
            $result = $uploader->testWebDAVConnection($alias);
            echo json_encode($result);
            exit;
            
        case 'get_logs':
            $logFile = $config->get('log_file', 'logs/app.log');
            $logs = [];

            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES);
                $lines = array_reverse(array_slice($lines, -50)); // 最近50条

                foreach ($lines as $line) {
                    $logData = json_decode($line, true);
                    if ($logData) {
                        $logs[] = $logData;
                    }
                }
            }

            echo json_encode(['success' => true, 'logs' => $logs]);
            exit;

        case 'update_config':
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    throw new Exception('无效的数据格式');
                }

                // 更新.env文件
                $envFile = '.env';
                $envContent = '';

                if (file_exists($envFile)) {
                    $envContent = file_get_contents($envFile);
                }

                // 更新配置项
                foreach ($data as $key => $value) {
                    $envKey = strtoupper($key);

                    // 特殊处理文件扩展名：统一转换为小写
                    if ($key === 'allowed_extensions') {
                        $extensions = array_map('trim', explode(',', $value));
                        $extensions = array_map('strtolower', $extensions);
                        $value = implode(', ', $extensions);
                    }

                    $pattern = "/^{$envKey}=.*$/m";
                    $replacement = "{$envKey}=" . $value;

                    if (preg_match($pattern, $envContent)) {
                        $envContent = preg_replace($pattern, $replacement, $envContent);
                    } else {
                        $envContent .= "\n{$replacement}";
                    }
                }

                file_put_contents($envFile, $envContent);
                echo json_encode(['success' => true, 'message' => '配置更新成功']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'add_webdav':
            try {
                $data = json_decode(file_get_contents('php://input'), true);
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
                file_put_contents($envFile, $envContent);

                echo json_encode(['success' => true, 'message' => 'WebDAV配置添加成功']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'delete_webdav':
            try {
                $alias = $_GET['alias'] ?? '';
                if (!$alias) {
                    throw new Exception('缺少别名参数');
                }

                $envFile = '.env';
                if (!file_exists($envFile)) {
                    throw new Exception('.env文件不存在');
                }

                $envContent = file_get_contents($envFile);
                $aliasUpper = strtoupper($alias);

                // 删除相关配置行
                $patterns = [
                    "/^WEBDAV_{$aliasUpper}_NAME=.*$/m",
                    "/^WEBDAV_{$aliasUpper}_URL=.*$/m",
                    "/^WEBDAV_{$aliasUpper}_USERNAME=.*$/m",
                    "/^WEBDAV_{$aliasUpper}_PASSWORD=.*$/m",
                    "/^# WebDAV配置 - .*$/m"
                ];

                foreach ($patterns as $pattern) {
                    $envContent = preg_replace($pattern, '', $envContent);
                }

                // 清理多余的空行
                $envContent = preg_replace("/\n\n+/", "\n\n", $envContent);

                file_put_contents($envFile, $envContent);
                echo json_encode(['success' => true, 'message' => 'WebDAV配置删除成功']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件床管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">文件床管理后台</span>
            <a href="?logout=1" class="btn btn-outline-light btn-sm">退出</a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- 系统配置 -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-gear"></i> 系统配置</h5>
                        <button class="btn btn-sm btn-primary" id="saveConfigBtn">
                            <i class="bi bi-save"></i> 保存配置
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="configForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="app_name" class="form-label">应用名称</label>
                                        <input type="text" class="form-control" id="app_name" name="app_name" value="<?= htmlspecialchars($config->get('app_name')) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="upload_max_size" class="form-label">最大上传大小 (字节)</label>
                                        <input type="number" class="form-control" id="upload_max_size" name="upload_max_size" value="<?= $config->get('upload_max_size') ?>">
                                        <div class="form-text">当前: <?= $config->formatFileSize($config->get('upload_max_size')) ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="default_webdav" class="form-label">默认WebDAV</label>
                                        <select class="form-control" id="default_webdav" name="default_webdav">
                                            <?php
                                            $webdavConfigs = $config->getWebDAVConfigs();
                                            $defaultWebdav = $config->get('default_webdav');
                                            foreach ($webdavConfigs as $alias => $webdavConfig) {
                                                $selected = $alias === $defaultWebdav ? 'selected' : '';
                                                echo "<option value=\"{$alias}\" {$selected}>{$webdavConfig['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="allowed_extensions" class="form-label">允许的文件类型</label>
                                        <textarea class="form-control" id="allowed_extensions" name="allowed_extensions" rows="3"><?= implode(', ', $config->get('allowed_extensions')) ?></textarea>
                                        <div class="form-text">用逗号分隔，如: jpg, png, pdf, txt（支持大小写，系统会自动统一为小写）</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="app_debug" name="app_debug" <?= $config->get('app_debug') ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="app_debug">
                                                启用调试模式
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="log_enabled" name="log_enabled" <?= $config->get('log_enabled') ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="log_enabled">
                                                启用日志记录
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- WebDAV配置管理 -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-server"></i> WebDAV存储管理</h5>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addWebdavModal">
                            <i class="bi bi-plus"></i> 添加WebDAV
                        </button>
                    </div>
                    <div class="card-body">
                        <?php
                        $webdavConfigs = $config->getWebDAVConfigs();
                        if (empty($webdavConfigs)): ?>
                            <div class="alert alert-warning">没有配置WebDAV存储</div>
                        <?php else: ?>
                            <div class="row" id="webdavList">
                                <?php foreach ($webdavConfigs as $alias => $webdavConfig): ?>
                                    <div class="col-md-6 mb-3" data-alias="<?= $alias ?>">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6><?= htmlspecialchars($webdavConfig['name']) ?></h6>
                                                        <p class="text-muted small mb-1"><?= htmlspecialchars($webdavConfig['url']) ?></p>
                                                        <p class="text-muted small">用户: <?= htmlspecialchars($webdavConfig['username']) ?></p>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i class="bi bi-three-dots"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item test-webdav" href="#" data-alias="<?= $alias ?>"><i class="bi bi-wifi"></i> 测试连接</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-danger delete-webdav" href="#" data-alias="<?= $alias ?>"><i class="bi bi-trash"></i> 删除</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="test-result mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 日志查看 -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-file-text"></i> 系统日志</h5>
                        <button class="btn btn-sm btn-outline-primary" id="refreshLogs">
                            <i class="bi bi-arrow-clockwise"></i> 刷新
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="logsContainer">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">加载中...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加WebDAV模态框 -->
    <div class="modal fade" id="addWebdavModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加WebDAV存储</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addWebdavForm">
                        <div class="mb-3">
                            <label for="webdav_alias" class="form-label">别名</label>
                            <input type="text" class="form-control" id="webdav_alias" name="alias" required>
                            <div class="form-text">用于标识此WebDAV配置的唯一别名，只能包含字母、数字和下划线</div>
                        </div>
                        <div class="mb-3">
                            <label for="webdav_name" class="form-label">显示名称</label>
                            <input type="text" class="form-control" id="webdav_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="webdav_url" class="form-label">WebDAV URL</label>
                            <input type="url" class="form-control" id="webdav_url" name="url" required>
                            <div class="form-text">例如: https://example.com/dav/</div>
                        </div>
                        <div class="mb-3">
                            <label for="webdav_username" class="form-label">用户名</label>
                            <input type="text" class="form-control" id="webdav_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="webdav_password" class="form-label">密码</label>
                            <input type="password" class="form-control" id="webdav_password" name="password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveWebdavBtn">保存</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 保存系统配置
        document.getElementById('saveConfigBtn').addEventListener('click', async function() {
            const form = document.getElementById('configForm');
            const formData = new FormData(form);
            const data = {};

            for (let [key, value] of formData.entries()) {
                if (key === 'app_debug' || key === 'log_enabled') {
                    data[key] = form.querySelector(`[name="${key}"]`).checked ? 'true' : 'false';
                } else {
                    data[key] = value;
                }
            }

            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> 保存中...';

            try {
                const response = await fetch('?action=update_config', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    alert('配置保存成功！');
                    location.reload();
                } else {
                    alert('保存失败: ' + result.error);
                }
            } catch (error) {
                alert('保存失败: ' + error.message);
            }

            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save"></i> 保存配置';
        });

        // 测试WebDAV连接
        function bindWebdavEvents() {
            document.querySelectorAll('.test-webdav').forEach(button => {
                button.addEventListener('click', async function(e) {
                    e.preventDefault();
                    const alias = this.dataset.alias;
                    const card = this.closest('.card');
                    const resultDiv = card.querySelector('.test-result');

                    this.disabled = true;
                    this.innerHTML = '<i class="bi bi-hourglass-split"></i> 测试中...';

                    try {
                        const response = await fetch(`?action=test_webdav&alias=${alias}`);
                        const result = await response.json();

                        if (result.success) {
                            resultDiv.innerHTML = '<span class="badge bg-success">连接成功</span>';
                        } else {
                            resultDiv.innerHTML = `<span class="badge bg-danger">连接失败: ${result.error}</span>`;
                        }
                    } catch (error) {
                        resultDiv.innerHTML = '<span class="badge bg-danger">测试失败</span>';
                    }

                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-wifi"></i> 测试连接';
                });
            });

            // 删除WebDAV
            document.querySelectorAll('.delete-webdav').forEach(button => {
                button.addEventListener('click', async function(e) {
                    e.preventDefault();
                    const alias = this.dataset.alias;

                    if (!confirm('确定要删除这个WebDAV配置吗？')) {
                        return;
                    }

                    try {
                        const response = await fetch(`?action=delete_webdav&alias=${alias}`);
                        const result = await response.json();

                        if (result.success) {
                            alert('删除成功！');
                            location.reload();
                        } else {
                            alert('删除失败: ' + result.error);
                        }
                    } catch (error) {
                        alert('删除失败: ' + error.message);
                    }
                });
            });
        }

        // 初始化事件绑定
        bindWebdavEvents();

        // 添加WebDAV
        document.getElementById('saveWebdavBtn').addEventListener('click', async function() {
            const form = document.getElementById('addWebdavForm');
            const formData = new FormData(form);
            const data = {};

            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }

            // 验证别名格式
            if (!/^[a-zA-Z0-9_]+$/.test(data.alias)) {
                alert('别名只能包含字母、数字和下划线');
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> 保存中...';

            try {
                const response = await fetch('?action=add_webdav', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    alert('WebDAV配置添加成功！');
                    location.reload();
                } else {
                    alert('添加失败: ' + result.error);
                }
            } catch (error) {
                alert('添加失败: ' + error.message);
            }

            this.disabled = false;
            this.innerHTML = '保存';
        });

        // 加载日志
        async function loadLogs() {
            const container = document.getElementById('logsContainer');
            
            try {
                const response = await fetch('?action=get_logs');
                const result = await response.json();
                
                if (result.success && result.logs.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>时间</th><th>级别</th><th>IP</th><th>消息</th></tr></thead><tbody>';
                    
                    result.logs.forEach(log => {
                        const levelClass = log.level === 'ERROR' ? 'danger' : (log.level === 'WARNING' ? 'warning' : 'info');
                        html += `<tr>
                            <td>${log.timestamp}</td>
                            <td><span class="badge bg-${levelClass}">${log.level}</span></td>
                            <td>${log.ip}</td>
                            <td>${log.message}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="alert alert-info">暂无日志记录</div>';
                }
            } catch (error) {
                container.innerHTML = '<div class="alert alert-danger">加载日志失败</div>';
            }
        }

        // 刷新日志
        document.getElementById('refreshLogs').addEventListener('click', loadLogs);

        // 页面加载时获取日志
        loadLogs();
    </script>
</body>
</html>

<?php
// 处理退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>
