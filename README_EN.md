# PHP File Hosting - WebDAV File Hosting System

A PHP-based file hosting system using WebDAV as backend storage, supporting multi-account configuration, file upload, online preview, admin interface and more.

## Features

### Core Features
- 🚀 **File Upload**: Drag & drop upload, progress display, batch upload support
- 📁 **WebDAV Storage**: Uses WebDAV as backend storage, no local space consumption
- 🔗 **Link Generation**: Auto-generate download links and direct links
- 👁️ **Online Preview**: Support for images, text, PDF and other file types
- 📱 **Responsive Design**: Support for desktop and mobile devices

### Management Features
- ⚙️ **System Configuration**: Visual configuration of application parameters
- 🗄️ **WebDAV Management**: Add, delete, test WebDAV storage accounts
- 📊 **Log Monitoring**: Real-time system logs and operation records
- 🔐 **Security Authentication**: Admin password protection

### Advanced Features
- 🔄 **Multi-WebDAV Support**: Support for multiple WebDAV storage accounts
- 🛡️ **Security Protection**: File type validation, size limits, malicious code detection
- 📈 **API Support**: RESTful API with curl command support
- ⚡ **Performance Optimization**: Smart caching, resumable downloads

## Installation & Deployment

### Requirements
- PHP 7.4+
- cURL extension
- Writable logs directory

### Quick Start

1. **Clone Project**
```bash
git clone https://github.com/your-username/php-file-hosting.git
cd php-file-hosting
```

2. **Setup Environment**
```bash
cp .env.example .env
```

3. **Edit Configuration**
```bash
nano .env
```

Configure your WebDAV information:
```env
# Application Configuration
APP_NAME="PHP File Hosting"
APP_URL=http://your-domain.com

# WebDAV Configuration
DEFAULT_WEBDAV=your_webdav_alias
WEBDAV_YOUR_ALIAS_NAME="Your WebDAV Storage"
WEBDAV_YOUR_ALIAS_URL="https://your-webdav-server.com/dav/"
WEBDAV_YOUR_ALIAS_USERNAME="your_username"
WEBDAV_YOUR_ALIAS_PASSWORD="your_password"

# Admin Password
ADMIN_PASSWORD=your_admin_password
```

4. **Set Permissions**
```bash
chmod 755 .
chmod 666 .env
mkdir -p logs
chmod 777 logs
```

5. **Access Application**
- Homepage: `http://your-domain.com/`
- Admin Panel: `http://your-domain.com/admin.php`

## Usage Guide

### File Upload
1. Visit homepage
2. Select or drag files to upload area
3. Wait for upload completion
4. Copy generated links

### Admin Panel
1. Visit `/admin.php`
2. Enter admin password
3. Available functions:
   - Modify system configuration
   - Manage WebDAV storage
   - View system logs
   - Test connection status

### API Usage
```bash
# Upload file
curl -F "file=@example.jpg" http://your-domain.com/api.php

# Upload with parameters
curl -F "file=@example.jpg" -F "webdav=backup" http://your-domain.com/api.php
```

## Configuration

### Basic Configuration
- `APP_NAME`: Application name
- `APP_DEBUG`: Debug mode
- `UPLOAD_MAX_SIZE`: Maximum upload size (bytes)
- `ALLOWED_EXTENSIONS`: Allowed file extensions (case-insensitive)

### WebDAV Configuration
Support multiple WebDAV storage, format:
```env
WEBDAV_ALIAS_NAME="Display Name"
WEBDAV_ALIAS_URL="WebDAV Server URL"
WEBDAV_ALIAS_USERNAME="Username"
WEBDAV_ALIAS_PASSWORD="Password"
WEBDAV_ALIAS_BASE_PATH="/upload/path/"
```

### Security Configuration
- `API_KEY_REQUIRED`: Whether API key is required
- `RATE_LIMIT_ENABLED`: Whether to enable rate limiting
- `ADMIN_PASSWORD`: Admin password

## Supported WebDAV Services

- ✅ TeraCloud
- ✅ Nextcloud
- ✅ ownCloud
- ✅ Nutstore (坚果云)
- ✅ Other standard WebDAV services

## Technical Architecture

- **Frontend**: Bootstrap 5 + Vanilla JavaScript
- **Backend**: PHP 7.4+
- **Storage**: WebDAV Protocol
- **Configuration**: Environment Variables (.env)
- **Logging**: JSON structured logging

## Development

Welcome to submit Issues and Pull Requests!

### Development Environment
```bash
# Enable debug mode
echo "APP_DEBUG=true" >> .env

# View logs
tail -f logs/app.log
```

## License

MIT License

## Changelog

### v1.0.0
- Basic file upload functionality
- WebDAV storage support
- Admin panel
- API interface
- Online preview functionality
