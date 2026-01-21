/**
 * Admin Panel JavaScript
 * SK Bakers Admin Panel
 */

// Environment Detection
const adminHostname = window.location.hostname;
const isLocalhost = adminHostname === 'localhost' ||
                    adminHostname === '127.0.0.1' ||
                    adminHostname.includes('192.168.') ||
                    adminHostname.includes('.local');

// Production domains
const ADMIN_PRODUCTION_DOMAINS = [
    'skbakers.in',
    'www.skbakers.in',
    'plum-grouse-536264.hostingersite.com'
];

// Check if current hostname is a production domain
const isAdminProduction = ADMIN_PRODUCTION_DOMAINS.some(domain => adminHostname.includes(domain));

// Configuration - Auto-detect environment
const CONFIG = {
    // API Base URL changes based on environment
    API_BASE: isLocalhost
        ? 'http://localhost/chicken-shop-app/backend/api/index.php/admin'
        : '/backend/api/index.php/admin',
    // Upload endpoint (separate for clarity)
    UPLOAD_BASE: isLocalhost
        ? 'http://localhost/chicken-shop-app/backend/api/index.php/admin'
        : '/backend/api/index.php/admin',
    SESSION_KEY: 'admin_session',
    ADMIN_KEY: 'admin_data'
};

// Log environment for debugging
console.log(`[SK Bakers Admin] Environment: ${isLocalhost ? 'LOCAL' : 'PRODUCTION'}`);
console.log(`[SK Bakers Admin] Hostname: ${adminHostname}`);
console.log(`[SK Bakers Admin] API Base: ${CONFIG.API_BASE}`);

// State
const AdminState = {
    admin: null,
    sessionToken: null,
    currentPage: 'dashboard'
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Check authentication
    const session = localStorage.getItem(CONFIG.SESSION_KEY);
    const admin = localStorage.getItem(CONFIG.ADMIN_KEY);

    if (session && admin) {
        AdminState.sessionToken = session;
        AdminState.admin = JSON.parse(admin);
        verifySession();
    } else {
        // Redirect to login if not on login page
        if (!window.location.pathname.includes('login.html')) {
            window.location.href = 'login.html';
        }
    }

    // Initialize UI
    initSidebar();
    initModals();
});

// Session verification
async function verifySession() {
    try {
        const response = await apiCall('/auth/verify', 'POST');

        if (response.success) {
            AdminState.admin = response.data.admin;
            localStorage.setItem(CONFIG.ADMIN_KEY, JSON.stringify(response.data.admin));
            updateAdminUI();
        } else {
            logout();
        }
    } catch (error) {
        console.error('Session verification failed:', error);
        // Don't logout on network errors, just update UI
        updateAdminUI();
    }
}

// Login function
async function login(username, password) {
    try {
        showLoading();
        const response = await apiCall('/auth/login', 'POST', {
            username: username,
            password: password
        });

        if (response.success) {
            AdminState.sessionToken = response.data.session_token;
            AdminState.admin = response.data.admin;
            localStorage.setItem(CONFIG.SESSION_KEY, response.data.session_token);
            localStorage.setItem(CONFIG.ADMIN_KEY, JSON.stringify(response.data.admin));
            showToast('Login successful!', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 500);
        } else {
            showToast(response.message || 'Login failed', 'error');
        }
    } catch (error) {
        showToast('Login failed. Please try again.', 'error');
    } finally {
        hideLoading();
    }
}

// Logout function
function logout() {
    apiCall('/auth/logout', 'POST').catch(() => {});

    localStorage.removeItem(CONFIG.SESSION_KEY);
    localStorage.removeItem(CONFIG.ADMIN_KEY);
    AdminState.sessionToken = null;
    AdminState.admin = null;
    window.location.href = 'login.html';
}

// API call helper - Laravel style routes
async function apiCall(endpoint, method = 'GET', data = null) {
    let url = `${CONFIG.API_BASE}${endpoint}`;
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };

    if (AdminState.sessionToken) {
        options.headers['Authorization'] = `Bearer ${AdminState.sessionToken}`;
    }

    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }

    if (method === 'GET' && data) {
        const params = new URLSearchParams(data);
        url += '?' + params.toString();
    }

    const response = await fetch(url, options);

    // Handle non-JSON responses gracefully
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('API returned non-JSON response:', text);
        return {
            success: false,
            message: 'Server returned an invalid response. Please try again.'
        };
    }
}

// UI Functions
function initSidebar() {
    const toggleBtn = document.querySelector('.toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            // For desktop - toggle collapsed state
            if (window.innerWidth > 576) {
                sidebar.classList.toggle('collapsed');
            } else {
                // For mobile - toggle open state and show overlay
                sidebar.classList.toggle('open');
                if (overlay) {
                    overlay.classList.toggle('active');
                }
            }
        });
    }

    // Close sidebar when clicking a nav item on mobile
    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 576) {
                closeSidebar();
            }
        });
    });

    // Navigation
    const dataNavItems = document.querySelectorAll('.nav-item[data-page]');
    dataNavItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const page = item.getAttribute('data-page');
            navigateTo(page);
        });
    });
}

// Close sidebar (for mobile)
function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        sidebar.classList.remove('open');
    }
    if (overlay) {
        overlay.classList.remove('active');
    }
}

function navigateTo(page) {
    window.location.href = page + '.html';
}

function initModals() {
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay.id);
            }
        });
    });

    // Close modal on close button click
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function updateAdminUI() {
    const adminName = document.querySelector('.admin-name');
    const adminRole = document.querySelector('.admin-role');
    const adminAvatar = document.querySelector('.admin-avatar');

    if (adminName && AdminState.admin) {
        adminName.textContent = AdminState.admin.name;
    }
    if (adminRole && AdminState.admin) {
        adminRole.textContent = AdminState.admin.role.charAt(0).toUpperCase() + AdminState.admin.role.slice(1);
    }
    if (adminAvatar && AdminState.admin) {
        adminAvatar.textContent = AdminState.admin.name.charAt(0).toUpperCase();
    }
}

// Toast notifications
function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Loading indicator
function showLoading(target = null) {
    if (target) {
        target.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Loading...</p></div>';
    } else {
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.innerHTML = '<div class="loading"><div class="loading-spinner"></div></div>';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;z-index:9999;';
        document.body.appendChild(overlay);
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

// Format currency
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

// Format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

// Format date time
function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Status badge HTML
function getStatusBadge(status) {
    return `<span class="status-badge ${status}">${status.replace(/_/g, ' ')}</span>`;
}

// Confirm dialog
function confirmAction(message) {
    return new Promise((resolve) => {
        if (confirm(message)) {
            resolve(true);
        } else {
            resolve(false);
        }
    });
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ========================================
// CENTRALIZED IMAGE UPLOAD FUNCTIONALITY
// ========================================

/**
 * Initialize image upload for a container
 * @param {Object} config - Configuration object
 * @param {string} config.containerId - ID of the upload container
 * @param {string} config.previewId - ID of the preview image element
 * @param {string} config.placeholderId - ID of the upload placeholder
 * @param {string} config.fileInputId - ID of the file input element
 * @param {string} config.hiddenInputId - ID of the hidden input for URL
 * @param {string} config.clearBtnId - ID of the clear button
 * @param {string} config.progressId - ID of the progress container
 * @param {string} config.progressFillId - ID of the progress fill element
 * @param {string} config.progressTextId - ID of the progress text element
 * @param {function} config.onUploadSuccess - Callback on successful upload
 */
function initImageUploader(config) {
    const container = document.getElementById(config.containerId);
    const preview = document.getElementById(config.previewId)?.parentElement || document.getElementById(config.containerId)?.querySelector('.image-preview');
    const fileInput = document.getElementById(config.fileInputId);

    if (!container || !fileInput) {
        console.warn('Image uploader: Required elements not found', config);
        return null;
    }

    let isUploading = false;

    // Click to upload
    if (preview) {
        preview.addEventListener('click', () => {
            if (!isUploading) fileInput.click();
        });
    }

    // File input change
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            uploadFile(e.target.files[0]);
        }
    });

    // Drag and drop
    container.addEventListener('dragover', (e) => {
        e.preventDefault();
        container.classList.add('dragover');
    });

    container.addEventListener('dragleave', (e) => {
        e.preventDefault();
        container.classList.remove('dragover');
    });

    container.addEventListener('drop', (e) => {
        e.preventDefault();
        container.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            uploadFile(e.dataTransfer.files[0]);
        }
    });

    function uploadFile(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Invalid file type. Allowed: JPG, PNG, GIF, WEBP', 'error');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            showToast('File too large. Maximum 5MB allowed', 'error');
            return;
        }

        isUploading = true;
        const progress = document.getElementById(config.progressId);
        const progressFill = document.getElementById(config.progressFillId);
        const progressText = document.getElementById(config.progressTextId);

        if (progress) {
            progress.style.display = 'block';
            if (progressFill) progressFill.style.width = '0%';
            if (progressText) progressText.textContent = 'Uploading...';
        }

        const formData = new FormData();
        formData.append('image', file);

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable && progressFill && progressText) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = percent + '%';
                progressText.textContent = 'Uploading... ' + percent + '%';
            }
        });

        xhr.addEventListener('load', () => {
            isUploading = false;
            if (progress) progress.style.display = 'none';

            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        setPreview(response.data.url);
                        showToast('Image uploaded successfully', 'success');
                        if (config.onUploadSuccess) {
                            config.onUploadSuccess(response.data.url);
                        }
                    } else {
                        showToast(response.message || 'Upload failed', 'error');
                    }
                } catch (e) {
                    showToast('Upload failed: Invalid response', 'error');
                }
            } else {
                showToast('Upload failed', 'error');
            }
        });

        xhr.addEventListener('error', () => {
            isUploading = false;
            if (progress) progress.style.display = 'none';
            showToast('Upload failed', 'error');
        });

        xhr.open('POST', CONFIG.API_BASE + '/upload');
        xhr.setRequestHeader('Authorization', 'Bearer ' + AdminState.sessionToken);
        xhr.send(formData);
    }

    function setPreview(url) {
        const previewImg = document.getElementById(config.previewId);
        const placeholder = document.getElementById(config.placeholderId);
        const clearBtn = document.getElementById(config.clearBtnId);
        const hiddenInput = document.getElementById(config.hiddenInputId);

        if (previewImg) {
            // Use getAdminImagePath to fix the path for admin pages
            previewImg.src = getAdminImagePath(url);
            previewImg.style.display = 'block';
        }
        if (placeholder) placeholder.style.display = 'none';
        if (clearBtn) clearBtn.style.display = 'inline-flex';
        // Store original URL (without path conversion) for API submission
        if (hiddenInput) hiddenInput.value = url;
    }

    function clearPreview() {
        const previewImg = document.getElementById(config.previewId);
        const placeholder = document.getElementById(config.placeholderId);
        const clearBtn = document.getElementById(config.clearBtnId);
        const hiddenInput = document.getElementById(config.hiddenInputId);

        if (previewImg) {
            previewImg.removeAttribute('src');
            previewImg.style.display = 'none';
        }
        if (placeholder) placeholder.style.display = 'block';
        if (clearBtn) clearBtn.style.display = 'none';
        if (hiddenInput) hiddenInput.value = '';
        if (fileInput) fileInput.value = '';
    }

    // Return controller object
    return {
        setPreview,
        clearPreview,
        isUploading: () => isUploading
    };
}

/**
 * Generate HTML for image upload component
 * @param {string} prefix - Prefix for element IDs (e.g., 'product', 'banner', 'popup')
 * @returns {string} HTML string
 */
function getImageUploadHTML(prefix) {
    return `
        <div class="image-upload-container" id="${prefix}UploadContainer">
            <div class="image-preview" id="${prefix}ImagePreview">
                <img id="${prefix}PreviewImg" src="" alt="Preview" style="display: none;">
                <div class="upload-placeholder" id="${prefix}Placeholder">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload or drag and drop</p>
                    <span>JPG, PNG, GIF, WEBP (Max 5MB)</span>
                </div>
            </div>
            <input type="file" id="${prefix}FileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
            <input type="hidden" id="${prefix}ImageUrl">
            <div class="upload-actions">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('${prefix}FileInput').click()">
                    <i class="fas fa-upload"></i> Choose File
                </button>
                <button type="button" class="btn btn-outline btn-sm" id="${prefix}ClearBtn" style="display: none;">
                    <i class="fas fa-times"></i> Remove
                </button>
            </div>
            <div class="upload-progress" id="${prefix}Progress" style="display: none;">
                <div class="progress-bar"><div class="progress-fill" id="${prefix}ProgressFill"></div></div>
                <span id="${prefix}ProgressText">Uploading...</span>
            </div>
        </div>
    `;
}

/**
 * Create standard config for image uploader based on prefix
 * @param {string} prefix - Prefix for element IDs
 * @returns {Object} Configuration object
 */
function getImageUploaderConfig(prefix) {
    return {
        containerId: `${prefix}UploadContainer`,
        previewId: `${prefix}PreviewImg`,
        placeholderId: `${prefix}Placeholder`,
        fileInputId: `${prefix}FileInput`,
        hiddenInputId: `${prefix}ImageUrl`,
        clearBtnId: `${prefix}ClearBtn`,
        progressId: `${prefix}Progress`,
        progressFillId: `${prefix}ProgressFill`,
        progressTextId: `${prefix}ProgressText`
    };
}

// Helper function to fix image paths for admin panel
// Images are uploaded with paths relative to frontend pages (../assets/uploads/)
// But admin pages are in frontend/pages/admin/, so they need ../../assets/uploads/
function getAdminImagePath(imagePath) {
    if (!imagePath) return '../../assets/logo.png';

    // Normalize path - convert backslashes to forward slashes
    let normalizedPath = imagePath.replace(/\\/g, '/');

    // If it's an absolute URL or data URL, return as is
    if (normalizedPath.startsWith('http') || normalizedPath.startsWith('data:')) {
        return normalizedPath;
    }

    // Handle Hostinger production absolute path format: /frontend/assets/uploads/filename.ext
    if (normalizedPath.startsWith('/frontend/assets/uploads/')) {
        const filename = normalizedPath.split('/frontend/assets/uploads/').pop();
        return '../../assets/uploads/' + filename;
    }

    // Extract just the filename from the path for uploads
    // This handles all variations: ../assets/uploads/x.png, ../../assets/uploads/x.png, assets/uploads/x.png
    if (normalizedPath.includes('assets/uploads/')) {
        const filename = normalizedPath.split('assets/uploads/').pop();
        return '../../assets/uploads/' + filename;
    }

    // Handle general assets paths
    if (normalizedPath.includes('assets/')) {
        // Extract path after 'assets/'
        const assetPath = normalizedPath.split('assets/').pop();
        return '../../assets/' + assetPath;
    }

    // If it's just a filename (e.g., "Prawns.jpg" or "product_xxx.png"), add proper path
    if (!normalizedPath.includes('/') || normalizedPath.match(/^[^\/]+\.(jpg|jpeg|png|gif|webp)$/i)) {
        // Try uploads folder first for uploaded images
        if (normalizedPath.startsWith('product_') || normalizedPath.startsWith('banner_') || normalizedPath.startsWith('popup_')) {
            return '../../assets/uploads/' + normalizedPath;
        }
        // Default to assets folder for other images
        return '../../assets/' + normalizedPath;
    }

    // Default fallback - return as is
    return normalizedPath;
}

// Export functions for use in other scripts
window.AdminAPI = {
    call: apiCall,
    login,
    logout,
    showToast,
    showLoading,
    hideLoading,
    formatCurrency,
    formatDate,
    formatDateTime,
    getStatusBadge,
    confirmAction,
    openModal,
    closeModal,
    debounce,
    state: AdminState,
    // Image upload functions
    initImageUploader,
    getImageUploadHTML,
    getImageUploaderConfig,
    // Image path helper
    getAdminImagePath
};
