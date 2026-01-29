/**
 * AI BotKit Chat Media Handler
 *
 * Handles rich media functionality for chat messages including:
 * - Image upload with preview and lightbox
 * - Video embeds (YouTube/Vimeo)
 * - File attachments with download cards
 * - Link previews with OpenGraph metadata
 * - Drag & drop support
 *
 * @package AI_BotKit
 * @since 2.0.0
 *
 * Implements: FR-220 to FR-229 (Rich Media Support)
 */

(function ($) {
    'use strict';

    /**
     * Media Handler Class
     */
    class AIBotkitMediaHandler {
        /**
         * Constructor
         *
         * @param {Object} options Configuration options
         */
        constructor(options = {}) {
            this.options = {
                maxFileSize: 10 * 1024 * 1024, // 10MB
                allowedImageTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                allowedDocTypes: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                thumbnailMaxSize: 400,
                lightboxEnabled: true,
                dragDropEnabled: true,
                linkPreviewEnabled: true,
                videoEmbedEnabled: true,
                nonce: '',
                ajaxUrl: '',
                ...options
            };

            this.currentUploads = new Map();
            this.lightboxActive = false;
            this.lightboxImages = [];
            this.lightboxIndex = 0;

            this.init();
        }

        /**
         * Initialize media handler
         */
        init() {
            this.createUI();
            this.bindEvents();

            if (this.options.dragDropEnabled) {
                this.initDragDrop();
            }

            if (this.options.lightboxEnabled) {
                this.initLightbox();
            }
        }

        /**
         * Create UI elements
         */
        createUI() {
            // Create upload button if container exists
            const chatInput = document.querySelector('.ai-botkit-chat-input');
            if (chatInput) {
                // Create media button
                const mediaBtn = document.createElement('button');
                mediaBtn.type = 'button';
                mediaBtn.className = 'ai-botkit-media-btn';
                mediaBtn.setAttribute('aria-label', 'Attach media');
                mediaBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                    </svg>
                `;

                // Create hidden file input
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.className = 'ai-botkit-media-input';
                fileInput.accept = [...this.options.allowedImageTypes, ...this.options.allowedDocTypes].join(',');
                fileInput.style.display = 'none';

                // Insert into DOM
                const inputWrapper = chatInput.parentElement;
                if (inputWrapper) {
                    inputWrapper.insertBefore(mediaBtn, chatInput);
                    inputWrapper.appendChild(fileInput);
                }

                this.mediaBtn = mediaBtn;
                this.fileInput = fileInput;
            }

            // Create media preview container
            const chatContainer = document.querySelector('.ai-botkit-chat-container');
            if (chatContainer) {
                const previewContainer = document.createElement('div');
                previewContainer.className = 'ai-botkit-media-preview-container';
                previewContainer.style.display = 'none';
                chatContainer.appendChild(previewContainer);
                this.previewContainer = previewContainer;
            }

            // Create lightbox overlay
            this.createLightbox();
        }

        /**
         * Create lightbox element
         */
        createLightbox() {
            const lightbox = document.createElement('div');
            lightbox.className = 'ai-botkit-lightbox';
            lightbox.innerHTML = `
                <div class="ai-botkit-lightbox-overlay"></div>
                <div class="ai-botkit-lightbox-container">
                    <button class="ai-botkit-lightbox-close" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <button class="ai-botkit-lightbox-nav ai-botkit-lightbox-prev" aria-label="Previous">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <div class="ai-botkit-lightbox-content">
                        <img src="" alt="" />
                    </div>
                    <button class="ai-botkit-lightbox-nav ai-botkit-lightbox-next" aria-label="Next">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            `;

            document.body.appendChild(lightbox);
            this.lightbox = lightbox;
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Media button click
            if (this.mediaBtn) {
                this.mediaBtn.addEventListener('click', () => {
                    this.fileInput.click();
                });
            }

            // File input change
            if (this.fileInput) {
                this.fileInput.addEventListener('change', (e) => {
                    if (e.target.files && e.target.files.length > 0) {
                        this.handleFileSelect(e.target.files[0]);
                    }
                });
            }

            // Click handler for lightbox triggers
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('.ai-botkit-lightbox-trigger');
                if (trigger) {
                    e.preventDefault();
                    this.openLightbox(trigger.href);
                }
            });

            // Lightbox controls
            if (this.lightbox) {
                this.lightbox.querySelector('.ai-botkit-lightbox-close').addEventListener('click', () => {
                    this.closeLightbox();
                });

                this.lightbox.querySelector('.ai-botkit-lightbox-overlay').addEventListener('click', () => {
                    this.closeLightbox();
                });

                this.lightbox.querySelector('.ai-botkit-lightbox-prev').addEventListener('click', () => {
                    this.prevImage();
                });

                this.lightbox.querySelector('.ai-botkit-lightbox-next').addEventListener('click', () => {
                    this.nextImage();
                });
            }

            // Keyboard events
            document.addEventListener('keydown', (e) => {
                if (this.lightboxActive) {
                    if (e.key === 'Escape') {
                        this.closeLightbox();
                    } else if (e.key === 'ArrowLeft') {
                        this.prevImage();
                    } else if (e.key === 'ArrowRight') {
                        this.nextImage();
                    }
                }
            });

            // Paste handler for images
            document.addEventListener('paste', (e) => {
                const chatInput = document.querySelector('.ai-botkit-chat-input:focus');
                if (chatInput && e.clipboardData.files.length > 0) {
                    e.preventDefault();
                    this.handleFileSelect(e.clipboardData.files[0]);
                }
            });
        }

        /**
         * Initialize drag and drop
         */
        initDragDrop() {
            const chatContainer = document.querySelector('.ai-botkit-chat-container');
            if (!chatContainer) return;

            // Create drop overlay
            const dropOverlay = document.createElement('div');
            dropOverlay.className = 'ai-botkit-drop-overlay';
            dropOverlay.innerHTML = `
                <div class="ai-botkit-drop-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <span>Drop file to upload</span>
                </div>
            `;
            chatContainer.appendChild(dropOverlay);

            let dragCounter = 0;

            chatContainer.addEventListener('dragenter', (e) => {
                e.preventDefault();
                dragCounter++;
                dropOverlay.classList.add('active');
            });

            chatContainer.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dragCounter--;
                if (dragCounter === 0) {
                    dropOverlay.classList.remove('active');
                }
            });

            chatContainer.addEventListener('dragover', (e) => {
                e.preventDefault();
            });

            chatContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                dragCounter = 0;
                dropOverlay.classList.remove('active');

                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    this.handleFileSelect(e.dataTransfer.files[0]);
                }
            });
        }

        /**
         * Initialize lightbox
         */
        initLightbox() {
            // Collect all images in chat for navigation
            this.updateLightboxImages();

            // Watch for new messages
            const observer = new MutationObserver(() => {
                this.updateLightboxImages();
            });

            const messagesContainer = document.querySelector('.ai-botkit-messages');
            if (messagesContainer) {
                observer.observe(messagesContainer, {
                    childList: true,
                    subtree: true
                });
            }
        }

        /**
         * Update lightbox images array
         */
        updateLightboxImages() {
            this.lightboxImages = Array.from(
                document.querySelectorAll('.ai-botkit-lightbox-trigger')
            ).map(el => el.href);
        }

        /**
         * Handle file selection
         *
         * @param {File} file Selected file
         */
        handleFileSelect(file) {
            // Validate file
            const validation = this.validateFile(file);
            if (!validation.valid) {
                this.showError(validation.error);
                return;
            }

            // Show preview
            this.showUploadPreview(file);

            // Upload file
            this.uploadFile(file);
        }

        /**
         * Validate file
         *
         * @param {File} file File to validate
         * @returns {Object} Validation result
         */
        validateFile(file) {
            // Check size
            if (file.size > this.options.maxFileSize) {
                return {
                    valid: false,
                    error: `File exceeds maximum size of ${this.formatFileSize(this.options.maxFileSize)}`
                };
            }

            // Check type
            const allowedTypes = [...this.options.allowedImageTypes, ...this.options.allowedDocTypes];
            if (!allowedTypes.includes(file.type)) {
                return {
                    valid: false,
                    error: 'File type not allowed. Allowed types: JPG, PNG, GIF, WebP, PDF, DOC, DOCX'
                };
            }

            return { valid: true };
        }

        /**
         * Show upload preview
         *
         * @param {File} file File being uploaded
         */
        showUploadPreview(file) {
            if (!this.previewContainer) return;

            const previewId = 'upload_' + Date.now();
            const isImage = this.options.allowedImageTypes.includes(file.type);

            const preview = document.createElement('div');
            preview.className = 'ai-botkit-upload-preview';
            preview.dataset.uploadId = previewId;

            if (isImage) {
                // Show image thumbnail
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.innerHTML = `
                        <div class="ai-botkit-preview-image">
                            <img src="${e.target.result}" alt="${file.name}" />
                        </div>
                        <div class="ai-botkit-preview-info">
                            <span class="ai-botkit-preview-name">${this.truncateFilename(file.name)}</span>
                            <span class="ai-botkit-preview-size">${this.formatFileSize(file.size)}</span>
                        </div>
                        <div class="ai-botkit-preview-progress">
                            <div class="ai-botkit-progress-bar"></div>
                        </div>
                        <button class="ai-botkit-preview-cancel" aria-label="Cancel upload">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                // Show file icon
                preview.innerHTML = `
                    <div class="ai-botkit-preview-file">
                        ${this.getFileIcon(file.name)}
                    </div>
                    <div class="ai-botkit-preview-info">
                        <span class="ai-botkit-preview-name">${this.truncateFilename(file.name)}</span>
                        <span class="ai-botkit-preview-size">${this.formatFileSize(file.size)}</span>
                    </div>
                    <div class="ai-botkit-preview-progress">
                        <div class="ai-botkit-progress-bar"></div>
                    </div>
                    <button class="ai-botkit-preview-cancel" aria-label="Cancel upload">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                `;
            }

            // Cancel button handler
            preview.querySelector('.ai-botkit-preview-cancel').addEventListener('click', () => {
                this.cancelUpload(previewId);
            });

            this.previewContainer.appendChild(preview);
            this.previewContainer.style.display = 'flex';

            return previewId;
        }

        /**
         * Upload file to server
         *
         * @param {File} file File to upload
         */
        uploadFile(file) {
            const formData = new FormData();
            formData.append('action', 'ai_botkit_upload_chat_media');
            formData.append('nonce', this.options.nonce);
            formData.append('media', file);

            // Get conversation ID if available
            const conversationId = this.getCurrentConversationId();
            if (conversationId) {
                formData.append('conversation_id', conversationId);
            }

            const xhr = new XMLHttpRequest();
            const previewId = 'upload_' + Date.now();

            this.currentUploads.set(previewId, xhr);

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    this.updateProgress(previewId, percent);
                }
            });

            xhr.addEventListener('load', () => {
                this.currentUploads.delete(previewId);

                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        this.onUploadSuccess(previewId, response.data.media);
                    } else {
                        this.onUploadError(previewId, response.data.message);
                    }
                } catch (e) {
                    this.onUploadError(previewId, 'Upload failed');
                }
            });

            xhr.addEventListener('error', () => {
                this.currentUploads.delete(previewId);
                this.onUploadError(previewId, 'Network error');
            });

            xhr.open('POST', this.options.ajaxUrl);
            xhr.send(formData);
        }

        /**
         * Update upload progress
         *
         * @param {string} previewId Upload preview ID
         * @param {number} percent Progress percentage
         */
        updateProgress(previewId, percent) {
            const preview = this.previewContainer.querySelector(`[data-upload-id="${previewId}"]`);
            if (preview) {
                const progressBar = preview.querySelector('.ai-botkit-progress-bar');
                if (progressBar) {
                    progressBar.style.width = `${percent}%`;
                }
            }
        }

        /**
         * Handle upload success
         *
         * @param {string} previewId Upload preview ID
         * @param {Object} media Media data from server
         */
        onUploadSuccess(previewId, media) {
            const preview = this.previewContainer.querySelector(`[data-upload-id="${previewId}"]`);
            if (preview) {
                preview.classList.add('success');
                preview.querySelector('.ai-botkit-preview-progress').style.display = 'none';
            }

            // Store media data for message attachment
            this.pendingMedia = this.pendingMedia || [];
            this.pendingMedia.push(media);

            // Trigger event for chat integration
            const event = new CustomEvent('ai-botkit:media-uploaded', {
                detail: { media }
            });
            document.dispatchEvent(event);
        }

        /**
         * Handle upload error
         *
         * @param {string} previewId Upload preview ID
         * @param {string} error Error message
         */
        onUploadError(previewId, error) {
            const preview = this.previewContainer.querySelector(`[data-upload-id="${previewId}"]`);
            if (preview) {
                preview.classList.add('error');
                preview.querySelector('.ai-botkit-preview-progress').innerHTML = `
                    <span class="ai-botkit-preview-error">${error}</span>
                `;
            }

            this.showError(error);
        }

        /**
         * Cancel upload
         *
         * @param {string} previewId Upload preview ID
         */
        cancelUpload(previewId) {
            const xhr = this.currentUploads.get(previewId);
            if (xhr) {
                xhr.abort();
                this.currentUploads.delete(previewId);
            }

            const preview = this.previewContainer.querySelector(`[data-upload-id="${previewId}"]`);
            if (preview) {
                preview.remove();
            }

            // Hide container if empty
            if (this.previewContainer.children.length === 0) {
                this.previewContainer.style.display = 'none';
            }
        }

        /**
         * Process URL for video embed
         *
         * @param {string} url Video URL
         * @returns {Promise} Video data promise
         */
        async processVideoUrl(url) {
            const formData = new FormData();
            formData.append('action', 'ai_botkit_process_video_url');
            formData.append('nonce', this.options.nonce);
            formData.append('url', url);

            const response = await fetch(this.options.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                return data.data.video;
            }

            throw new Error(data.data.message);
        }

        /**
         * Fetch link preview
         *
         * @param {string} url URL to preview
         * @returns {Promise} Preview data promise
         */
        async fetchLinkPreview(url) {
            const formData = new FormData();
            formData.append('action', 'ai_botkit_get_link_preview');
            formData.append('nonce', this.options.nonce);
            formData.append('url', url);

            const response = await fetch(this.options.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                return data.data.preview;
            }

            throw new Error(data.data.message);
        }

        /**
         * Open lightbox
         *
         * @param {string} imageUrl Image URL
         */
        openLightbox(imageUrl) {
            this.lightboxActive = true;
            this.lightboxIndex = this.lightboxImages.indexOf(imageUrl);

            const img = this.lightbox.querySelector('.ai-botkit-lightbox-content img');
            img.src = imageUrl;

            this.lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Update nav visibility
            this.updateLightboxNav();

            // Trap focus
            this.lightbox.querySelector('.ai-botkit-lightbox-close').focus();
        }

        /**
         * Close lightbox
         */
        closeLightbox() {
            this.lightboxActive = false;
            this.lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }

        /**
         * Show previous image in lightbox
         */
        prevImage() {
            if (this.lightboxIndex > 0) {
                this.lightboxIndex--;
                const img = this.lightbox.querySelector('.ai-botkit-lightbox-content img');
                img.src = this.lightboxImages[this.lightboxIndex];
                this.updateLightboxNav();
            }
        }

        /**
         * Show next image in lightbox
         */
        nextImage() {
            if (this.lightboxIndex < this.lightboxImages.length - 1) {
                this.lightboxIndex++;
                const img = this.lightbox.querySelector('.ai-botkit-lightbox-content img');
                img.src = this.lightboxImages[this.lightboxIndex];
                this.updateLightboxNav();
            }
        }

        /**
         * Update lightbox navigation visibility
         */
        updateLightboxNav() {
            const prevBtn = this.lightbox.querySelector('.ai-botkit-lightbox-prev');
            const nextBtn = this.lightbox.querySelector('.ai-botkit-lightbox-next');

            prevBtn.style.display = this.lightboxIndex > 0 ? 'flex' : 'none';
            nextBtn.style.display = this.lightboxIndex < this.lightboxImages.length - 1 ? 'flex' : 'none';
        }

        /**
         * Render image attachment
         *
         * @param {Object} media Media data
         * @returns {string} HTML string
         */
        renderImage(media) {
            const thumbUrl = media.metadata?.thumbnail_url || media.url;
            const altText = media.metadata?.alt_text || 'Chat image';

            return `
                <div class="ai-botkit-media-image" data-media-id="${media.id}">
                    <a href="${media.url}" class="ai-botkit-lightbox-trigger" data-lightbox="chat-media">
                        <img src="${thumbUrl}" alt="${altText}" loading="lazy" />
                    </a>
                </div>
            `;
        }

        /**
         * Render video embed
         *
         * @param {Object} video Video data
         * @returns {string} HTML string
         */
        renderVideo(video) {
            return `
                <div class="ai-botkit-media-video" data-media-id="${video.media_id || ''}">
                    <div class="ai-botkit-video-wrapper">
                        <iframe src="${video.embed_url}"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                    </div>
                </div>
            `;
        }

        /**
         * Render document attachment
         *
         * @param {Object} media Media data
         * @returns {string} HTML string
         */
        renderDocument(media) {
            const extension = media.file_name.split('.').pop();
            const size = this.formatFileSize(media.file_size);

            return `
                <div class="ai-botkit-media-document" data-media-id="${media.id}">
                    <div class="ai-botkit-file-card">
                        <div class="ai-botkit-file-icon">${this.getFileIcon(media.file_name)}</div>
                        <div class="ai-botkit-file-info">
                            <span class="ai-botkit-file-name">${this.truncateFilename(media.file_name)}</span>
                            <span class="ai-botkit-file-size">${extension.toUpperCase()} - ${size}</span>
                        </div>
                        <a href="${media.download_url || media.url}" class="ai-botkit-file-download" download>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </a>
                    </div>
                </div>
            `;
        }

        /**
         * Render link preview
         *
         * @param {Object} preview Link preview data
         * @returns {string} HTML string
         */
        renderLinkPreview(preview) {
            let imageHtml = '';
            if (preview.image) {
                imageHtml = `
                    <div class="ai-botkit-link-image">
                        <img src="${preview.image}" alt="" loading="lazy" />
                    </div>
                `;
            }

            return `
                <div class="ai-botkit-media-link" data-media-id="${preview.media_id || ''}">
                    <a href="${preview.url}" target="_blank" rel="noopener noreferrer" class="ai-botkit-link-card">
                        ${imageHtml}
                        <div class="ai-botkit-link-content">
                            ${preview.site_name ? `<span class="ai-botkit-link-site">${preview.site_name}</span>` : ''}
                            ${preview.title ? `<span class="ai-botkit-link-title">${preview.title}</span>` : ''}
                            ${preview.description ? `<span class="ai-botkit-link-description">${this.truncateText(preview.description, 150)}</span>` : ''}
                        </div>
                    </a>
                </div>
            `;
        }

        /**
         * Get current conversation ID
         *
         * @returns {string|null} Conversation ID
         */
        getCurrentConversationId() {
            const container = document.querySelector('.ai-botkit-chat-container');
            return container?.dataset.conversationId || null;
        }

        /**
         * Format file size
         *
         * @param {number} bytes File size in bytes
         * @returns {string} Formatted size
         */
        formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        /**
         * Truncate filename
         *
         * @param {string} filename Filename
         * @param {number} maxLength Maximum length
         * @returns {string} Truncated filename
         */
        truncateFilename(filename, maxLength = 30) {
            if (filename.length <= maxLength) return filename;

            const ext = filename.split('.').pop();
            const name = filename.slice(0, -(ext.length + 1));
            const available = maxLength - ext.length - 4;

            return name.slice(0, available) + '...' + '.' + ext;
        }

        /**
         * Truncate text
         *
         * @param {string} text Text to truncate
         * @param {number} length Maximum length
         * @returns {string} Truncated text
         */
        truncateText(text, length = 150) {
            if (text.length <= length) return text;
            return text.slice(0, length) + '...';
        }

        /**
         * Get file icon SVG
         *
         * @param {string} filename Filename
         * @returns {string} SVG icon
         */
        getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();

            const icons = {
                pdf: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                doc: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
                docx: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'
            };

            return icons[ext] || '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        }

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError(message) {
            // Trigger error event for chat to handle
            const event = new CustomEvent('ai-botkit:media-error', {
                detail: { message }
            });
            document.dispatchEvent(event);

            console.error('[AI BotKit Media]', message);
        }

        /**
         * Get pending media for message attachment
         *
         * @returns {Array} Pending media array
         */
        getPendingMedia() {
            const media = this.pendingMedia || [];
            this.pendingMedia = [];
            return media;
        }

        /**
         * Clear preview container
         */
        clearPreviews() {
            if (this.previewContainer) {
                this.previewContainer.innerHTML = '';
                this.previewContainer.style.display = 'none';
            }
        }
    }

    // Export to global scope
    window.AIBotkitMediaHandler = AIBotkitMediaHandler;

    // Auto-initialize if settings are available
    $(document).ready(function () {
        if (typeof aiBotKitSettings !== 'undefined') {
            window.aiBotKitMedia = new AIBotkitMediaHandler({
                nonce: aiBotKitSettings.nonce,
                ajaxUrl: aiBotKitSettings.ajaxUrl,
                maxFileSize: aiBotKitSettings.maxMediaSize || 10 * 1024 * 1024
            });
        }
    });

})(jQuery);
