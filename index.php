<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP WebDAV 文件床</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            margin: 2rem auto;
            max-width: 800px;
        }
        
        .upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
            cursor: pointer;
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: #0d6efd;
            background: #e7f3ff;
            transform: translateY(-2px);
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .upload-area.dragover .upload-icon {
            color: #0d6efd;
            animation: bounce 0.6s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }
        
        .progress-container {
            display: none;
            margin-top: 2rem;
        }
        
        .result-container {
            display: none;
            margin-top: 2rem;
        }
        
        .file-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .btn-copy {
            transition: all 0.3s ease;
        }
        
        .btn-copy:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .webdav-selector {
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
        }
        
        .preview-container {
            max-width: 100%;
            margin-top: 1rem;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="main-container p-4">
            <div class="card-header text-center py-3 mb-4">
                <h1 class="mb-0">
                    <i class="bi bi-cloud-upload"></i>
                    PHP WebDAV 文件床
                </h1>
                <p class="mb-0 mt-2 opacity-75">安全、快速、可靠的文件存储服务</p>
            </div>
            
            <!-- WebDAV选择器 -->
            <div class="webdav-selector">
                <label for="webdavSelect" class="form-label fw-bold">
                    <i class="bi bi-server"></i> 选择存储服务
                </label>
                <select class="form-select" id="webdavSelect">
                    <option value="">加载中...</option>
                </select>
            </div>
            
            <!-- 上传区域 -->
            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <h4>拖拽文件到此处或点击选择文件</h4>
                <p class="text-muted mb-3">支持图片、文档、压缩包等多种格式</p>
                <button type="button" class="btn btn-primary btn-lg">
                    <i class="bi bi-folder2-open"></i> 选择文件
                </button>
                <input type="file" id="fileInput" class="d-none" multiple>
            </div>
            
            <!-- 进度条 -->
            <div class="progress-container" id="progressContainer">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold">上传进度</span>
                    <span id="progressText">0%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         id="progressBar" role="progressbar" style="width: 0%"></div>
                </div>
                <div class="text-center mt-3">
                    <div class="loading-spinner" id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">上传中...</span>
                        </div>
                        <p class="mt-2 text-muted">正在上传文件，请稍候...</p>
                    </div>
                </div>
            </div>

            <!-- 上传结果 -->
            <div class="result-container fade-in" id="resultContainer">
                <div class="alert alert-success" role="alert">
                    <h5 class="alert-heading">
                        <i class="bi bi-check-circle"></i> 上传成功！
                    </h5>
                    <div id="fileInfo" class="file-info">
                        <!-- 文件信息将在这里显示 -->
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <button class="btn btn-primary btn-copy" id="copyUrlBtn">
                            <i class="bi bi-clipboard"></i> 复制链接
                        </button>
                        <button class="btn btn-outline-primary" id="previewBtn" style="display: none;">
                            <i class="bi bi-eye"></i> 预览
                        </button>
                        <button class="btn btn-outline-secondary" id="uploadAnotherBtn">
                            <i class="bi bi-plus-circle"></i> 继续上传
                        </button>
                    </div>

                    <div class="preview-container" id="previewContainer">
                        <!-- 预览内容将在这里显示 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast 通知 -->
    <div class="toast-container">
        <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="bi bi-info-circle text-primary me-2"></i>
                <strong class="me-auto">通知</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toastBody">
                <!-- 通知内容 -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class FileUploader {
            constructor() {
                this.uploadArea = document.getElementById('uploadArea');
                this.fileInput = document.getElementById('fileInput');
                this.webdavSelect = document.getElementById('webdavSelect');
                this.progressContainer = document.getElementById('progressContainer');
                this.progressBar = document.getElementById('progressBar');
                this.progressText = document.getElementById('progressText');
                this.loadingSpinner = document.getElementById('loadingSpinner');
                this.resultContainer = document.getElementById('resultContainer');
                this.fileInfo = document.getElementById('fileInfo');
                this.copyUrlBtn = document.getElementById('copyUrlBtn');
                this.previewBtn = document.getElementById('previewBtn');
                this.previewContainer = document.getElementById('previewContainer');
                this.uploadAnotherBtn = document.getElementById('uploadAnotherBtn');

                this.currentDownloadUrl = '';
                this.currentFileData = null;

                this.initEventListeners();
                this.loadWebDAVConfigs();
            }

            initEventListeners() {
                // 点击上传区域选择文件
                this.uploadArea.addEventListener('click', () => {
                    this.fileInput.click();
                });

                // 文件选择
                this.fileInput.addEventListener('change', (e) => {
                    this.handleFiles(e.target.files);
                });

                // 拖拽事件
                this.uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    this.uploadArea.classList.add('dragover');
                });

                this.uploadArea.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    this.uploadArea.classList.remove('dragover');
                });

                this.uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    this.uploadArea.classList.remove('dragover');
                    this.handleFiles(e.dataTransfer.files);
                });

                // 复制链接
                this.copyUrlBtn.addEventListener('click', () => {
                    this.copyToClipboard(this.currentDownloadUrl);
                });

                // 预览按钮
                this.previewBtn.addEventListener('click', () => {
                    this.showPreview();
                });

                // 继续上传
                this.uploadAnotherBtn.addEventListener('click', () => {
                    this.resetUploader();
                });
            }

            async loadWebDAVConfigs() {
                try {
                    const response = await fetch('api.php?action=webdav_list');
                    const result = await response.json();

                    if (result.success) {
                        this.populateWebDAVSelect(result.data);
                    } else {
                        this.showToast('加载WebDAV配置失败', 'error');
                    }
                } catch (error) {
                    this.showToast('网络错误，无法加载配置', 'error');
                }
            }

            populateWebDAVSelect(configs) {
                this.webdavSelect.innerHTML = '';

                if (configs.length === 0) {
                    this.webdavSelect.innerHTML = '<option value="">没有可用的存储配置</option>';
                    return;
                }

                configs.forEach(config => {
                    const option = document.createElement('option');
                    option.value = config.alias;
                    option.textContent = config.name;
                    this.webdavSelect.appendChild(option);
                });

                // 选择第一个配置
                if (configs.length > 0) {
                    this.webdavSelect.value = configs[0].alias;
                }
            }

            handleFiles(files) {
                if (files.length === 0) return;

                // 目前只处理第一个文件
                const file = files[0];
                this.uploadFile(file);
            }

            async uploadFile(file) {
                const webdavAlias = this.webdavSelect.value;

                if (!webdavAlias) {
                    this.showToast('请选择存储服务', 'error');
                    return;
                }

                // 显示进度条
                this.showProgress();

                const formData = new FormData();
                formData.append('file', file);
                formData.append('webdav', webdavAlias);

                try {
                    const xhr = new XMLHttpRequest();

                    // 上传进度
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            this.updateProgress(percentComplete);
                        }
                    });

                    // 上传完成
                    xhr.addEventListener('load', () => {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (result.success) {
                                this.showResult(result.data);
                            } else {
                                this.showToast(result.error || '上传失败', 'error');
                                this.hideProgress();
                            }
                        } catch (error) {
                            this.showToast('响应解析错误', 'error');
                            this.hideProgress();
                        }
                    });

                    // 上传错误
                    xhr.addEventListener('error', () => {
                        this.showToast('网络错误，上传失败', 'error');
                        this.hideProgress();
                    });

                    xhr.open('POST', 'api.php');
                    xhr.send(formData);

                } catch (error) {
                    this.showToast('上传过程中发生错误', 'error');
                    this.hideProgress();
                }
            }

            showProgress() {
                this.uploadArea.style.display = 'none';
                this.progressContainer.style.display = 'block';
                this.loadingSpinner.style.display = 'block';
                this.resultContainer.style.display = 'none';
            }

            hideProgress() {
                this.progressContainer.style.display = 'none';
                this.loadingSpinner.style.display = 'none';
                this.uploadArea.style.display = 'block';
            }

            updateProgress(percent) {
                this.progressBar.style.width = percent + '%';
                this.progressText.textContent = Math.round(percent) + '%';
            }

            showResult(data) {
                this.currentFileData = data;
                this.currentDownloadUrl = data.download_url;

                // 隐藏进度条
                this.hideProgress();

                // 显示文件信息
                this.fileInfo.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>原文件名:</strong> ${data.original_name}<br>
                            <strong>存储文件名:</strong> ${data.file_name}<br>
                            <strong>文件大小:</strong> ${data.file_size_formatted}
                        </div>
                        <div class="col-md-6">
                            <strong>存储服务:</strong> ${data.webdav_name}<br>
                            <strong>上传时间:</strong> ${data.upload_time}<br>
                            <strong>文件类型:</strong> ${data.mime_type}
                        </div>
                    </div>
                    <div class="mt-3">
                        <strong>访问链接:</strong><br>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" value="${data.download_url}" readonly id="downloadUrlInput">
                            <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('downloadUrlInput')">
                                <i class="bi bi-clipboard"></i> 复制
                            </button>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-2">
                            <a href="${data.download_url}" target="_blank" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye"></i> 在线预览
                            </a>
                            <a href="${data.download_url}&download=1" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download"></i> 强制下载
                            </a>
                        </div>

                        ${data.direct_url ? `
                            <small class="text-muted mt-1 d-block">
                                <a href="${data.direct_url}" target="_blank" class="text-decoration-none">
                                    <i class="bi bi-box-arrow-up-right"></i> WebDAV直链 (需要认证)
                                </a>
                            </small>
                        ` : ''}
                    </div>
                `;

                // 显示预览按钮（如果是图片）
                if (data.is_image) {
                    this.previewBtn.style.display = 'inline-block';
                } else {
                    this.previewBtn.style.display = 'none';
                }

                // 显示结果容器
                this.resultContainer.style.display = 'block';

                this.showToast('文件上传成功！', 'success');
            }

            showPreview() {
                if (this.currentFileData && this.currentFileData.is_image) {
                    this.previewContainer.innerHTML = `
                        <div class="text-center">
                            <img src="${this.currentDownloadUrl}" class="preview-image" alt="预览图片">
                        </div>
                    `;
                }
            }

            resetUploader() {
                this.uploadArea.style.display = 'block';
                this.progressContainer.style.display = 'none';
                this.resultContainer.style.display = 'none';
                this.previewContainer.innerHTML = '';
                this.fileInput.value = '';
                this.currentDownloadUrl = '';
                this.currentFileData = null;
            }

            async copyToClipboard(text) {
                try {
                    await navigator.clipboard.writeText(text);
                    this.showToast('链接已复制到剪贴板', 'success');
                } catch (error) {
                    // 降级方案
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    this.showToast('链接已复制到剪贴板', 'success');
                }
            }

            showToast(message, type = 'info') {
                const toast = document.getElementById('toast');
                const toastBody = document.getElementById('toastBody');
                const toastHeader = toast.querySelector('.toast-header i');

                toastBody.textContent = message;

                // 设置图标和颜色
                toastHeader.className = 'me-2';
                if (type === 'success') {
                    toastHeader.classList.add('bi', 'bi-check-circle', 'text-success');
                } else if (type === 'error') {
                    toastHeader.classList.add('bi', 'bi-exclamation-triangle', 'text-danger');
                } else {
                    toastHeader.classList.add('bi', 'bi-info-circle', 'text-primary');
                }

                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
            }
        }

        // 复制到剪贴板函数
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.select();
                element.setSelectionRange(0, 99999); // 移动端兼容

                try {
                    document.execCommand('copy');
                    // 显示成功提示
                    const toast = document.getElementById('toast');
                    const toastBody = document.getElementById('toastBody');
                    const toastHeader = toast.querySelector('.toast-header i');

                    toastBody.textContent = '链接已复制到剪贴板！';
                    toastHeader.className = 'me-2 bi bi-check-circle text-success';

                    const bsToast = new bootstrap.Toast(toast);
                    bsToast.show();
                } catch (err) {
                    console.error('复制失败:', err);
                    alert('复制失败，请手动复制');
                }
            }
        }

        // 初始化上传器
        document.addEventListener('DOMContentLoaded', () => {
            new FileUploader();
        });
    </script>
</body>
</html>
