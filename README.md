# PHP文件床 - WebDAV文件托管系统

### 完全由Augmentcode编写

一个基于PHP的文件托管系统，使用WebDAV作为后端存储，支持多账号配置、文件上传、在线预览、管理界面等功能。

## 功能特性

### 核心功能
- 🚀 **文件上传**：支持拖拽上传、进度显示、多文件批量上传
- 📁 **WebDAV存储**：使用WebDAV作为后端存储，不占用本地空间
- 🔗 **链接生成**：自动生成下载链接和直链
- 👁️ **在线预览**：支持图片、文本、PDF等文件在线预览
- 📱 **响应式设计**：支持桌面和移动设备

### 管理功能
- ⚙️ **系统配置**：可视化配置应用参数
- 🗄️ **WebDAV管理**：添加、删除、测试WebDAV存储
- 📊 **日志监控**：实时查看系统日志和操作记录
- 🔐 **安全认证**：管理员密码保护

### 高级特性
- 🔄 **多WebDAV支持**：支持配置多个WebDAV存储账号
- 🛡️ **安全防护**：文件类型验证、大小限制、恶意代码检测
- 📈 **API支持**：RESTful API，支持curl命令行上传
- ⚡ **性能优化**：智能缓存、断点续传支持

## 安装部署

### 环境要求
- PHP 7.4+
- cURL扩展
- 可写的logs目录

### 快速开始

1. **克隆项目**
\`\`\`bash
git clone https://github.com/your-username/php-file-hosting.git
cd php-file-hosting
\`\`\`

2. **配置环境**
\`\`\`bash
cp .env.example .env
\`\`\`

3. **编辑配置文件**
\`\`\`bash
nano .env
\`\`\`

配置您的WebDAV信息：
\`\`\`env
# 应用配置
APP_NAME="PHP文件床"
APP_URL=http://your-domain.com

# WebDAV配置
DEFAULT_WEBDAV=your_webdav_alias
WEBDAV_YOUR_ALIAS_NAME="您的WebDAV存储"
WEBDAV_YOUR_ALIAS_URL="https://your-webdav-server.com/dav/"
WEBDAV_YOUR_ALIAS_USERNAME="your_username"
WEBDAV_YOUR_ALIAS_PASSWORD="your_password"

# 管理员密码
ADMIN_PASSWORD=your_admin_password
\`\`\`

4. **设置权限**
\`\`\`bash
chmod 755 .
chmod 666 .env
mkdir -p logs
chmod 777 logs
\`\`\`

5. **访问应用**
- 主页：\`http://your-domain.com/\`
- 管理后台：\`http://your-domain.com/admin.php\`

## 使用说明

### 文件上传
1. 访问主页
2. 选择或拖拽文件到上传区域
3. 等待上传完成
4. 复制生成的链接

### 管理后台
1. 访问 \`/admin.php\`
2. 输入管理员密码
3. 可以：
   - 修改系统配置
   - 管理WebDAV存储
   - 查看系统日志
   - 测试连接状态

### API使用
\`\`\`bash
# 上传文件
curl -F "file=@example.jpg" http://your-domain.com/api.php

# 带参数上传
curl -F "file=@example.jpg" -F "webdav=backup" http://your-domain.com/api.php
\`\`\`

## 配置说明

### 基本配置
- \`APP_NAME\`: 应用名称
- \`APP_DEBUG\`: 调试模式
- \`UPLOAD_MAX_SIZE\`: 最大上传大小（字节）
- \`ALLOWED_EXTENSIONS\`: 允许的文件扩展名

### WebDAV配置
支持配置多个WebDAV存储，格式：
\`\`\`env
WEBDAV_ALIAS_NAME="显示名称"
WEBDAV_ALIAS_URL="WebDAV服务器URL"
WEBDAV_ALIAS_USERNAME="用户名"
WEBDAV_ALIAS_PASSWORD="密码"
WEBDAV_ALIAS_BASE_PATH="/上传路径/"
\`\`\`

### 安全配置
- \`API_KEY_REQUIRED\`: 是否需要API密钥
- \`RATE_LIMIT_ENABLED\`: 是否启用速率限制
- \`ADMIN_PASSWORD\`: 管理员密码

## 支持的WebDAV服务

- ✅ TeraCloud
- ✅ Nextcloud
- ✅ ownCloud
- ✅ 坚果云
- ✅ 其他标准WebDAV服务

## 技术架构

- **前端**: Bootstrap 5 + 原生JavaScript
- **后端**: PHP 7.4+
- **存储**: WebDAV协议
- **配置**: 环境变量(.env)
- **日志**: JSON格式结构化日志

## 开发贡献

欢迎提交Issue和Pull Request！

### 开发环境
\`\`\`bash
# 启用调试模式
echo "APP_DEBUG=true" >> .env

# 查看日志
tail -f logs/app.log
\`\`\`

## 许可证

MIT License

## 更新日志

### v1.0.0
- 基础文件上传功能
- WebDAV存储支持
- 管理后台
- API接口
- 在线预览功能
