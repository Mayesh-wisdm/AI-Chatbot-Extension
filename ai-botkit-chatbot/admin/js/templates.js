/**
 * AI BotKit Templates Management JavaScript
 *
 * Handles all template management functionality including CRUD operations,
 * applying templates to chatbots, and import/export.
 *
 * @package AI_BotKit\Admin
 * @since   2.0.0
 *
 * Implements: FR-230 to FR-239 (Conversation Templates)
 */

(function($) {
    'use strict';

    /**
     * Template Manager Class
     */
    class TemplateManager {
        constructor() {
            this.templates = [];
            this.currentTemplate = null;
            this.isSystemTemplate = false;
            this.starterIndex = 0;

            this.init();
        }

        /**
         * Initialize template manager
         */
        init() {
            this.bindEvents();
            this.loadTemplates();
        }

        /**
         * Bind all event handlers
         */
        bindEvents() {
            // Filter and sort events
            $('#ai-botkit-filter-category, #ai-botkit-filter-type, #ai-botkit-sort-by').on('change', () => this.loadTemplates());

            // Add template button
            $('.ai-botkit-add-template-btn').on('click', (e) => {
                e.preventDefault();
                this.openNewTemplateModal();
            });

            // Import button
            $('.ai-botkit-import-template-btn').on('click', (e) => {
                e.preventDefault();
                this.openImportModal();
            });

            // Modal close buttons
            $('.ai-botkit-modal-close, .ai-botkit-modal-cancel').on('click', () => this.closeModals());

            // Tab switching
            $(document).on('click', '.ai-botkit-tab-btn', (e) => this.switchTab(e));

            // Temperature slider
            $('#template-temperature').on('input', function() {
                $('#temperature-value').text($(this).val());
            });

            // Save template
            $('#save-template-btn').on('click', () => this.saveTemplate());
            $('#save-as-copy-btn').on('click', () => this.saveTemplateAsCopy());

            // Add conversation starter
            $('#add-starter-btn').on('click', () => this.addStarter());

            // Remove conversation starter
            $(document).on('click', '.ai-botkit-remove-starter', (e) => this.removeStarter(e));

            // Template card actions
            $(document).on('click', '.ai-botkit-edit-template', (e) => this.editTemplate(e));
            $(document).on('click', '.ai-botkit-duplicate-template', (e) => this.duplicateTemplate(e));
            $(document).on('click', '.ai-botkit-apply-template', (e) => this.openApplyModal(e));
            $(document).on('click', '.ai-botkit-export-template', (e) => this.exportTemplate(e));
            $(document).on('click', '.ai-botkit-delete-template', (e) => this.confirmDelete(e));

            // Apply template
            $('#apply-template-btn').on('click', () => this.applyTemplate());

            // Import template
            $('#import-template-btn').on('click', () => this.importTemplate());

            // Confirm delete
            $('#confirm-delete-btn').on('click', () => this.deleteTemplate());

            // Close modals on overlay click
            $('.ai-botkit-modal').on('click', (e) => {
                if ($(e.target).hasClass('ai-botkit-modal')) {
                    this.closeModals();
                }
            });

            // Close modals on Escape key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeModals();
                }
            });
        }

        /**
         * Load templates from server
         */
        loadTemplates() {
            const $grid = $('#ai-botkit-templates-grid');
            $grid.html('<div class="ai-botkit-loading"><span class="spinner is-active"></span> Loading templates...</div>');

            const filters = {
                category: $('#ai-botkit-filter-category').val(),
                orderby: $('#ai-botkit-sort-by').val()
            };

            const filterType = $('#ai-botkit-filter-type').val();
            if (filterType === 'system') {
                filters.is_system = true;
            } else if (filterType === 'custom') {
                filters.is_system = false;
            }

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_botkit_list_templates',
                    nonce: ai_botkit_admin.nonce,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        this.templates = response.data.templates;
                        this.renderTemplates();
                    } else {
                        this.showError(response.data.message || 'Failed to load templates.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                }
            });
        }

        /**
         * Render template cards
         */
        renderTemplates() {
            const $grid = $('#ai-botkit-templates-grid');
            const template = $('#template-card-template').html();

            if (!this.templates || this.templates.length === 0) {
                $grid.html('<div class="ai-botkit-no-items"><p>No templates found. Click "Add New Template" to create one.</p></div>');
                return;
            }

            let html = '';
            const categories = {
                support: 'Support',
                sales: 'Sales',
                marketing: 'Marketing',
                education: 'Education',
                general: 'General'
            };

            this.templates.forEach(item => {
                const primaryColor = item.style?.primary_color || '#4F46E5';
                const categoryLabel = categories[item.category] || item.category;

                let cardHtml = template
                    .replace(/{{id}}/g, item.id)
                    .replace(/{{name}}/g, this.escapeHtml(item.name))
                    .replace(/{{description}}/g, this.escapeHtml(item.description || ''))
                    .replace(/{{category}}/g, item.category)
                    .replace(/{{category_label}}/g, categoryLabel)
                    .replace(/{{primary_color}}/g, primaryColor)
                    .replace(/{{usage_count}}/g, item.usage_count || 0);

                // Handle system badge (positive conditional)
                if (item.is_system) {
                    cardHtml = cardHtml.replace(/\{\{#is_system\}\}([\s\S]*?)\{\{\/is_system\}\}/g, '$1');
                } else {
                    cardHtml = cardHtml.replace(/\{\{#is_system\}\}[\s\S]*?\{\{\/is_system\}\}/g, '');
                }

                // Handle delete button (inverted conditional)
                if (item.is_system) {
                    cardHtml = cardHtml.replace(/\{\{\^is_system\}\}[\s\S]*?\{\{\/is_system\}\}/g, '');
                } else {
                    cardHtml = cardHtml.replace(/\{\{\^is_system\}\}([\s\S]*?)\{\{\/is_system\}\}/g, '$1');
                }

                html += cardHtml;
            });

            $grid.html(html);
        }

        /**
         * Open new template modal
         */
        openNewTemplateModal() {
            this.currentTemplate = null;
            this.isSystemTemplate = false;

            // Reset form
            $('#ai-botkit-template-form')[0].reset();
            $('#template-id').val(0);
            $('#ai-botkit-modal-title').text('New Template');
            $('#conversation-starters-list').empty();
            this.starterIndex = 0;

            // Show/hide buttons
            $('#save-template-btn').show();
            $('#save-as-copy-btn').hide();
            $('#template-system-notice').hide();

            // Reset tab
            $('.ai-botkit-tab-btn').removeClass('active').first().addClass('active');
            $('.ai-botkit-tab-panel').removeClass('active').first().addClass('active');

            // Set default colors
            $('#template-primary-color').val('#4F46E5');
            $('#template-header-bg-color').val('#4F46E5');
            $('#template-header-color').val('#FFFFFF');
            $('#template-body-bg-color').val('#FFFFFF');
            $('#template-ai-msg-bg').val('#F3F4F6');
            $('#template-user-msg-bg').val('#4F46E5');
            $('#template-ai-msg-font').val('#1F2937');
            $('#template-user-msg-font').val('#FFFFFF');
            $('#temperature-value').text('0.5');

            $('#ai-botkit-template-modal').show();
        }

        /**
         * Edit template
         */
        editTemplate(e) {
            const templateId = $(e.target).closest('.ai-botkit-template-card').data('id');
            const template = this.templates.find(t => t.id == templateId);

            if (!template) {
                this.showError('Template not found.');
                return;
            }

            this.currentTemplate = template;
            this.isSystemTemplate = !!template.is_system;

            // Populate form
            $('#template-id').val(template.id);
            $('#ai-botkit-modal-title').text(this.isSystemTemplate ? 'View Template' : 'Edit Template');
            $('#template-name').val(template.name);
            $('#template-description').val(template.description || '');
            $('#template-category').val(template.category || 'general');
            $('#template-active').prop('checked', !!template.is_active);

            // Messages
            const messages = template.messages_template || {};
            $('#template-personality').val(messages.personality || '');
            $('#template-greeting').val(messages.greeting || '');
            $('#template-fallback').val(messages.fallback || '');

            // Style
            const style = template.style || {};
            $('#template-primary-color').val(style.primary_color || '#4F46E5');
            $('#template-header-bg-color').val(style.header_bg_color || '#4F46E5');
            $('#template-header-color').val(style.header_color || '#FFFFFF');
            $('#template-body-bg-color').val(style.body_bg_color || '#FFFFFF');
            $('#template-ai-msg-bg').val(style.ai_msg_bg_color || '#F3F4F6');
            $('#template-user-msg-bg').val(style.user_msg_bg_color || '#4F46E5');
            $('#template-ai-msg-font').val(style.ai_msg_font_color || '#1F2937');
            $('#template-user-msg-font').val(style.user_msg_font_color || '#FFFFFF');
            $('#template-font-family').val(style.font_family || 'system-ui, -apple-system, sans-serif');
            $('#template-position').val(style.position || 'bottom-right');

            // Model config
            const model = template.model_config || {};
            $('#template-model').val(model.model || 'gpt-4o-mini');
            $('#template-temperature').val(model.temperature || 0.5);
            $('#temperature-value').text(model.temperature || 0.5);
            $('#template-max-tokens').val(model.max_tokens || 800);
            $('#template-context-length').val(model.context_length || 5);
            $('#template-tone').val(model.tone || 'professional');

            // Conversation starters
            $('#conversation-starters-list').empty();
            this.starterIndex = 0;
            const starters = template.conversation_starters || [];
            starters.forEach(starter => {
                this.addStarter(starter.text, starter.icon);
            });

            // Show/hide buttons based on system template
            if (this.isSystemTemplate) {
                $('#save-template-btn').hide();
                $('#save-as-copy-btn').show();
                $('#template-system-notice').show();
            } else {
                $('#save-template-btn').show();
                $('#save-as-copy-btn').hide();
                $('#template-system-notice').hide();
            }

            // Reset tab
            $('.ai-botkit-tab-btn').removeClass('active').first().addClass('active');
            $('.ai-botkit-tab-panel').removeClass('active').first().addClass('active');

            $('#ai-botkit-template-modal').show();
        }

        /**
         * Save template
         */
        saveTemplate() {
            const formData = this.getFormData();

            if (!formData.name || !formData.name.trim()) {
                this.showError('Template name is required.');
                return;
            }

            const $btn = $('#save-template-btn');
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_botkit_save_template',
                    nonce: ai_botkit_admin.nonce,
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeModals();
                        this.loadTemplates();
                    } else {
                        this.showError(response.data.message || 'Failed to save template.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Save Template');
                }
            });
        }

        /**
         * Save template as copy (for system templates)
         */
        saveTemplateAsCopy() {
            const formData = this.getFormData();
            formData.template_id = 0; // Force create new
            formData.name = formData.name + ' (Copy)';

            const $btn = $('#save-as-copy-btn');
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_botkit_save_template',
                    nonce: ai_botkit_admin.nonce,
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Template copied successfully.');
                        this.closeModals();
                        this.loadTemplates();
                    } else {
                        this.showError(response.data.message || 'Failed to copy template.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Save as Copy');
                }
            });
        }

        /**
         * Get form data
         */
        getFormData() {
            return {
                template_id: $('#template-id').val(),
                name: $('#template-name').val(),
                description: $('#template-description').val(),
                category: $('#template-category').val(),
                is_active: $('#template-active').is(':checked') ? 1 : 0,
                messages_template: {
                    personality: $('#template-personality').val(),
                    greeting: $('#template-greeting').val(),
                    fallback: $('#template-fallback').val()
                },
                style: {
                    primary_color: $('#template-primary-color').val(),
                    header_bg_color: $('#template-header-bg-color').val(),
                    header_color: $('#template-header-color').val(),
                    body_bg_color: $('#template-body-bg-color').val(),
                    ai_msg_bg_color: $('#template-ai-msg-bg').val(),
                    user_msg_bg_color: $('#template-user-msg-bg').val(),
                    ai_msg_font_color: $('#template-ai-msg-font').val(),
                    user_msg_font_color: $('#template-user-msg-font').val(),
                    font_family: $('#template-font-family').val(),
                    position: $('#template-position').val()
                },
                model_config: {
                    model: $('#template-model').val(),
                    temperature: parseFloat($('#template-temperature').val()),
                    max_tokens: parseInt($('#template-max-tokens').val()),
                    context_length: parseInt($('#template-context-length').val()),
                    tone: $('#template-tone').val()
                },
                conversation_starters: this.getStarters()
            };
        }

        /**
         * Get conversation starters from form
         */
        getStarters() {
            const starters = [];
            $('#conversation-starters-list .ai-botkit-starter-item').each(function() {
                const text = $(this).find('input[type="text"]').val();
                const icon = $(this).find('select').val();
                if (text.trim()) {
                    starters.push({ text, icon });
                }
            });
            return starters;
        }

        /**
         * Add conversation starter
         */
        addStarter(text = '', icon = 'help-circle') {
            const template = $('#starter-item-template').html();
            let html = template
                .replace(/{{index}}/g, this.starterIndex)
                .replace(/{{text}}/g, this.escapeHtml(text));

            // Set icon selected
            const icons = ['help-circle', 'search', 'info', 'shopping-bag', 'user', 'message'];
            icons.forEach(iconName => {
                const selected = iconName === icon ? 'selected' : '';
                html = html.replace(`{{#is_${iconName.replace('-', '_')}}}selected{{/is_${iconName.replace('-', '_')}}}`, selected);
            });

            // Clean up remaining mustache tags
            html = html.replace(/\{\{#is_\w+\}\}selected\{\{\/is_\w+\}\}/g, '');

            $('#conversation-starters-list').append(html);
            this.starterIndex++;
        }

        /**
         * Remove conversation starter
         */
        removeStarter(e) {
            $(e.target).closest('.ai-botkit-starter-item').remove();
        }

        /**
         * Duplicate template
         */
        duplicateTemplate(e) {
            const templateId = $(e.target).closest('.ai-botkit-template-card').data('id');

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_botkit_duplicate_template',
                    nonce: ai_botkit_admin.nonce,
                    template_id: templateId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.loadTemplates();
                    } else {
                        this.showError(response.data.message || 'Failed to duplicate template.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                }
            });
        }

        /**
         * Open apply template modal
         */
        openApplyModal(e) {
            const templateId = $(e.target).closest('.ai-botkit-template-card').data('id');
            $('#apply-template-id').val(templateId);
            $('#apply-chatbot-select').val('');
            $('input[name="apply_mode"][value="merge"]').prop('checked', true);
            $('#ai-botkit-apply-modal').show();
        }

        /**
         * Apply template to chatbot
         */
        applyTemplate() {
            const templateId = $('#apply-template-id').val();
            const chatbotId = $('#apply-chatbot-select').val();
            const merge = $('input[name="apply_mode"]:checked').val() === 'merge';

            if (!chatbotId) {
                this.showError('Please select a chatbot.');
                return;
            }

            const $btn = $('#apply-template-btn');
            $btn.prop('disabled', true).text('Applying...');

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_botkit_apply_template',
                    nonce: ai_botkit_admin.nonce,
                    template_id: templateId,
                    chatbot_id: chatbotId,
                    merge: merge ? 1 : 0
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeModals();
                        this.loadTemplates();
                    } else {
                        this.showError(response.data.message || 'Failed to apply template.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Apply Template');
                }
            });
        }

        /**
         * Export template
         */
        exportTemplate(e) {
            const templateId = $(e.target).closest('.ai-botkit-template-card').data('id');

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_botkit_export_template',
                    nonce: ai_botkit_admin.nonce,
                    template_id: templateId
                },
                success: (response) => {
                    if (response.success) {
                        // Download JSON file
                        const blob = new Blob([response.data.json], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        this.showSuccess('Template exported successfully.');
                    } else {
                        this.showError(response.data.message || 'Failed to export template.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                }
            });
        }

        /**
         * Open import modal
         */
        openImportModal() {
            $('#import-file').val('');
            $('input[name="conflict_mode"][value="error"]').prop('checked', true);
            $('#ai-botkit-import-modal').show();
        }

        /**
         * Import template
         */
        importTemplate() {
            const fileInput = $('#import-file')[0];
            const conflictMode = $('input[name="conflict_mode"]:checked').val();

            if (!fileInput.files.length) {
                this.showError('Please select a file to import.');
                return;
            }

            const file = fileInput.files[0];
            if (!file.name.endsWith('.json')) {
                this.showError('Please select a valid JSON file.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'ai_botkit_import_template');
            formData.append('nonce', ai_botkit_admin.nonce);
            formData.append('template_file', file);
            formData.append('conflict_mode', conflictMode);

            const $btn = $('#import-template-btn');
            $btn.prop('disabled', true).text('Importing...');

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeModals();
                        this.loadTemplates();
                    } else {
                        this.showError(response.data.message || 'Failed to import template.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Import');
                }
            });
        }

        /**
         * Confirm delete
         */
        confirmDelete(e) {
            const $card = $(e.target).closest('.ai-botkit-template-card');
            const templateId = $card.data('id');
            const template = this.templates.find(t => t.id == templateId);

            $('#delete-template-id').val(templateId);
            $('.ai-botkit-template-name-confirm').text(template ? template.name : '');
            $('#ai-botkit-delete-modal').show();
        }

        /**
         * Delete template
         */
        deleteTemplate() {
            const templateId = $('#delete-template-id').val();

            const $btn = $('#confirm-delete-btn');
            $btn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: ai_botkit_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_botkit_delete_template',
                    nonce: ai_botkit_admin.nonce,
                    template_id: templateId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeModals();
                        this.loadTemplates();
                    } else {
                        this.showError(response.data.message || 'Failed to delete template.');
                    }
                },
                error: () => {
                    this.showError('Failed to connect to server.');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Delete');
                }
            });
        }

        /**
         * Switch tab
         */
        switchTab(e) {
            const tab = $(e.target).data('tab');
            $('.ai-botkit-tab-btn').removeClass('active');
            $(e.target).addClass('active');
            $('.ai-botkit-tab-panel').removeClass('active');
            $(`.ai-botkit-tab-panel[data-panel="${tab}"]`).addClass('active');
        }

        /**
         * Close all modals
         */
        closeModals() {
            $('.ai-botkit-modal').hide();
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            this.showNotice(message, 'success');
        }

        /**
         * Show error message
         */
        showError(message) {
            this.showNotice(message, 'error');
        }

        /**
         * Show notice
         */
        showNotice(message, type) {
            // Remove existing notices
            $('.ai-botkit-toast').remove();

            const $notice = $(`
                <div class="ai-botkit-toast ai-botkit-toast-${type}">
                    <span class="ai-botkit-toast-message">${this.escapeHtml(message)}</span>
                    <button type="button" class="ai-botkit-toast-close">&times;</button>
                </div>
            `);

            $('body').append($notice);
            $notice.fadeIn();

            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);

            // Close on click
            $notice.find('.ai-botkit-toast-close').on('click', () => {
                $notice.fadeOut(() => $notice.remove());
            });
        }

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize on templates page
        if ($('#ai-botkit-templates-grid').length) {
            new TemplateManager();
        }
    });

})(jQuery);
