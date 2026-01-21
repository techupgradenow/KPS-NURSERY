// Image Upload Module for Admin Panel
const ImageUpload = {
    isUploading: false,

    init: function(containerId, inputId, previewId) {
        const container = document.getElementById(containerId);
        const preview = document.getElementById(previewId);
        const fileInput = document.getElementById('imageFile');

        if (!container || !preview || !fileInput) return;

        // Click to upload
        preview.addEventListener('click', () => {
            if (!this.isUploading) fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) this.upload(e.target.files[0]);
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
            if (e.dataTransfer.files.length > 0) this.upload(e.dataTransfer.files[0]);
        });
    },

    upload: function(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            AdminAPI.showToast('Invalid file type. Allowed: JPG, PNG, GIF, WEBP', 'error');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            AdminAPI.showToast('File too large. Maximum 5MB allowed', 'error');
            return;
        }

        this.isUploading = true;
        const progress = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        progress.style.display = 'block';
        progressFill.style.width = '0%';
        progressText.textContent = 'Uploading...';

        const formData = new FormData();
        formData.append('image', file);

        const xhr = new XMLHttpRequest();
        const self = this;

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = percent + '%';
                progressText.textContent = 'Uploading... ' + percent + '%';
            }
        });

        xhr.addEventListener('load', () => {
            self.isUploading = false;
            progress.style.display = 'none';
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        self.setPreview(response.data.url);
                        AdminAPI.showToast('Image uploaded successfully', 'success');
                    } else {
                        AdminAPI.showToast(response.message || 'Upload failed', 'error');
                    }
                } catch (e) {
                    AdminAPI.showToast('Upload failed: Invalid response', 'error');
                }
            } else {
                AdminAPI.showToast('Upload failed', 'error');
            }
        });

        xhr.addEventListener('error', () => {
            self.isUploading = false;
            progress.style.display = 'none';
            AdminAPI.showToast('Upload failed', 'error');
        });

        xhr.open('POST', CONFIG.API_BASE + '/upload');
        xhr.setRequestHeader('Authorization', 'Bearer ' + AdminAPI.state.sessionToken);
        xhr.send(formData);
    },

    setPreview: function(url) {
        const previewImg = document.getElementById('previewImg');
        const placeholder = document.getElementById('uploadPlaceholder');
        const clearBtn = document.getElementById('clearImageBtn');
        const imageInput = document.getElementById('productImage');

        if (previewImg) {
            previewImg.src = url;
            previewImg.style.display = 'block';
        }
        if (placeholder) placeholder.style.display = 'none';
        if (clearBtn) clearBtn.style.display = 'inline-flex';
        if (imageInput) imageInput.value = url;
    },

    clear: function() {
        const previewImg = document.getElementById('previewImg');
        const placeholder = document.getElementById('uploadPlaceholder');
        const clearBtn = document.getElementById('clearImageBtn');
        const imageInput = document.getElementById('productImage');
        const fileInput = document.getElementById('imageFile');

        if (previewImg) {
            previewImg.src = '';
            previewImg.style.display = 'none';
        }
        if (placeholder) placeholder.style.display = 'block';
        if (clearBtn) clearBtn.style.display = 'none';
        if (imageInput) imageInput.value = '';
        if (fileInput) fileInput.value = '';
    }
};

// Global functions for onclick handlers
function clearImage() {
    ImageUpload.clear();
}

function setImagePreview(url) {
    ImageUpload.setPreview(url);
}
