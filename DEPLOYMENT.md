# 部署说明

## PHP WebDAV 文件床 - 部署指南

### 当前项目结构

```
.
├── 404.html              # 404错误页面
├── admin.php             # 管理后台
├── api.php               # API接口
├── composer.json         # Composer配置
├── download.php          # 下载页面
├── .env                  # 生产环境配置（已恢复）
├── .env.example          # 环境配置模板（已清理）
├── .gitignore           # Git忽略文件
├── .htaccess            # Apache配置
├── index.html           # 静态首页
├── index.php            # 主页面
├── LICENSE              # MIT许可证
├── proxy.php            # 代理下载
├── README.md            # 项目文档
└── src/                 # 源代码目录
    ├── Config.php       # 配置管理
    ├── FileUploader.php # 文件上传
    ├── Logger.php       # 日志系统
    ├── Security.php     # 安全验证
    └── WebDAVClient.php # WebDAV客户端
```

### 部署到GitHub的步骤

1. **初始化Git仓库**：
```bash
git init
git add .
git commit -m "Initial commit: PHP WebDAV File Hosting System"
```

2. **添加远程仓库**：
```bash
git remote add origin https://github.com/your-username/php-webdav-filebed.git
```

3. **推送到GitHub**：
```bash
git branch -M main
git push -u origin main
```

### 部署后的配置

用户下载项目后需要：

1. **复制环境配置**：
```bash
cp .env.example .env
```

2. **编辑配置文件**：
```bash
nano .env
```

3. **配置WebDAV信息**：
```env
# 应用配置
APP_NAME="PHP文件床"
APP_URL=http://your-domain.com

# WebDAV配置
DEFAULT_WEBDAV=your_webdav_alias
WEBDAV_YOUR_ALIAS_NAME="您的WebDAV存储"
WEBDAV_YOUR_ALIAS_URL="https://your-webdav-server.com/dav/"
WEBDAV_YOUR_ALIAS_USERNAME="your_username"
WEBDAV_YOUR_ALIAS_PASSWORD="your_password"
WEBDAV_YOUR_ALIAS_BASE_PATH="/uploads/"

# 管理员密码
ADMIN_PASSWORD=your_admin_password
```

4. **设置权限**：
```bash
chmod 666 .env
mkdir -p logs
chmod 777 logs
```

### 安全提醒

- ⚠️ 确保`.env`文件在`.gitignore`中，避免意外提交敏感信息
- ⚠️ 生产环境请使用强密码
- ⚠️ 定期更新WebDAV凭据
- ⚠️ 启用HTTPS以保护数据传输

### 功能验证

部署完成后，请验证以下功能：

- [ ] 文件上传功能
- [ ] 在线预览功能
- [ ] 下载链接生成
- [ ] 管理后台访问
- [ ] API接口调用
- [ ] 日志记录功能

### 技术支持

如有问题，请在GitHub仓库中提交Issue。

---

**项目状态**：✅ 已完成GitHub部署准备，可以安全推送到公开仓库
