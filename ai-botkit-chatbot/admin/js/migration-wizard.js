/**
 * AI BotKit Migration Wizard JavaScript
 * 
 * Handles the migration wizard modal and AJAX operations
 */

(function($) {
    'use strict';

    class MigrationWizard {
        constructor() {
            this.currentStep = 1;
            this.totalSteps = 4;
            this.migrationOptions = {};
            this.init();
        }

        init() {
            this.bindEvents();
            // Only load migration status if migration section is visible (Pinecone configured)
            if ($('.ai-botkit-migration-section').length > 0) {
                this.loadMigrationStatus();
            }
        }

        bindEvents() {
            // Migration button click
            $('#ai-botkit-migration-btn').on('click', () => {
                this.openWizard();
            });

            // Refresh status button
            $('#ai-botkit-refresh-status-btn').on('click', () => {
                this.loadMigrationStatus();
            });

            // Clear database buttons
            $('#ai-botkit-clear-local-btn').on('click', () => {
                this.clearDatabase('local');
            });

            $('#ai-botkit-clear-pinecone-btn').on('click', () => {
                this.clearDatabase('pinecone');
            });

            $('#ai-botkit-clear-knowledge-base-btn').on('click', () => {
                this.clearDatabase('knowledge_base');
            });

            // Clear stuck migration button (dynamically added)
            $(document).on('click', '#ai-botkit-clear-migration-lock', () => {
                this.clearMigrationLock();
            });

            // Modal close
            $('.ai-botkit-modal-close, #ai-botkit-migration-close').on('click', () => {
                this.closeWizard();
            });

            // Modal backdrop click
            $('#ai-botkit-migration-modal').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeWizard();
                }
            });

            // Navigation buttons
            $('#ai-botkit-migration-next').on('click', () => {
                this.nextStep();
            });

            $('#ai-botkit-migration-prev').on('click', () => {
                this.prevStep();
            });

            $('#ai-botkit-migration-start').on('click', () => {
                this.startMigration();
            });

            // Scope change handler
            $('input[name="migration_scope"]').on('change', () => {
                this.handleScopeChange();
            });

            // Direction change handler
            $('input[name="migration_direction"]').on('change', () => {
                this.handleDirectionChange();
            });
        }

        openWizard() {
            this.currentStep = 1;
            this.migrationOptions = {};
            this.showStep(1);
            $('#ai-botkit-migration-modal').show();
            this.updateNavigationButtons();
            
            // Reset button state and hide log
            $('#ai-botkit-migration-start').prop('disabled', false).removeClass('disabled');
            $('#migration-log').hide().removeClass('has-content');
        }

        closeWizard() {
            $('#ai-botkit-migration-modal').hide();
            this.resetWizard();
            // Reload migration status when closing to update main page
            this.loadMigrationStatus();
        }

        resetWizard() {
            this.currentStep = 1;
            this.migrationOptions = {};
            $('.ai-botkit-migration-step').hide();
            $('input[type="radio"]').prop('checked', false);
            $('input[type="checkbox"]').prop('checked', false);
            $('input[type="date"]').val('');
            $('#migration-summary-list').empty();
            $('#migration-log').empty();
            $('#migration-progress-fill').css('width', '0%');
            $('#migration-progress-text').text('Starting migration...');
        }

        nextStep() {
            if (this.validateCurrentStep()) {
                this.collectStepData();
                this.currentStep++;
                
                // Skip steps based on scope
                if (this.currentStep === 3 && this.migrationOptions.scope !== 'by_type') {
                    this.currentStep++; // Skip to step 4
                }
                
                this.showStep(this.currentStep);
                this.updateNavigationButtons();
            }
        }

        prevStep() {
            this.currentStep--;
            
            // Skip steps based on scope (in reverse)
            if (this.currentStep === 3 && this.migrationOptions.scope !== 'by_type') {
                this.currentStep--; // Skip to step 2
            }
            
            this.showStep(this.currentStep);
            this.updateNavigationButtons();
        }

        showStep(step) {
            $('.ai-botkit-migration-step').hide();
            $(`.ai-botkit-migration-step[data-step="${step}"]`).show();
            
            // Load content types when reaching step 3
            if (step === 3) {
                this.loadContentTypes();
            }
            
            // Update migration summary when reaching step 4
            if (step === 4) {
                this.updateMigrationSummary();
            }
        }
        
        updateMigrationSummary() {
            const summaryList = $('#migration-summary-list');
            summaryList.empty();
            
            // Add direction
            const directionText = this.migrationOptions.direction === 'to_pinecone' 
                ? 'Local Database → Pinecone' 
                : 'Pinecone → Local Database';
            summaryList.append(`<li><strong>Direction:</strong> ${directionText}</li>`);
            
            // Add scope
            const scopeText = this.migrationOptions.scope === 'all' 
                ? 'All Data' 
                : 'By Content Type';
            summaryList.append(`<li><strong>Scope:</strong> ${scopeText}</li>`);
            
            // Add content types if applicable
            if (this.migrationOptions.scope === 'by_type' && this.migrationOptions.content_types) {
                const types = this.migrationOptions.content_types.join(', ');
                summaryList.append(`<li><strong>Content Types:</strong> ${types}</li>`);
            }
            
        }

        updateNavigationButtons() {
            const prevBtn = $('#ai-botkit-migration-prev');
            const nextBtn = $('#ai-botkit-migration-next');
            const startBtn = $('#ai-botkit-migration-start');
            const closeBtn = $('#ai-botkit-migration-close');

            // Show/hide previous button
            if (this.currentStep > 1) {
                prevBtn.show();
            } else {
                prevBtn.hide();
            }

            // Show/hide next/start buttons
            if (this.currentStep < this.totalSteps) {
                nextBtn.show();
                startBtn.hide();
                closeBtn.hide();
            } else {
                nextBtn.hide();
                startBtn.show();
                closeBtn.hide();
            }

            // Update next button text
            if (this.currentStep === this.totalSteps - 1) {
                nextBtn.text('Review & Start');
            } else {
                nextBtn.text('Next');
            }
        }

        validateCurrentStep() {
            switch (this.currentStep) {
                case 1:
                    return $('input[name="migration_direction"]:checked').length > 0;
                case 2:
                    return $('input[name="migration_scope"]:checked').length > 0;
                case 3:
                    const scope = $('input[name="migration_scope"]:checked').val();
                    if (scope === 'by_type') {
                        return $('input[name="content_types[]"]:checked').length > 0;
                    }
                    return true;
                case 4:
                    return $('#migration_confirm').is(':checked');
                default:
                    return true;
            }
        }

        collectStepData() {
            switch (this.currentStep) {
                case 1:
                    this.migrationOptions.direction = $('input[name="migration_direction"]:checked').val();
                    break;
                case 2:
                    this.migrationOptions.scope = $('input[name="migration_scope"]:checked').val();
                    break;
                case 3:
                    if (this.migrationOptions.scope === 'by_type') {
                        this.migrationOptions.content_types = $('input[name="content_types[]"]:checked').map(function() {
                            return this.value;
                        }).get();
                    }
                    break;
                case 4:
                    // Confirmation step - no data to collect
                    break;
            }
        }

        handleScopeChange() {
            const scope = $('input[name="migration_scope"]:checked').val();
            
            if (scope === 'by_type') {
                this.loadContentTypes();
            }
        }

        handleDirectionChange() {
            // Update UI based on direction
            const direction = $('input[name="migration_direction"]:checked').val();
            // Could add direction-specific UI updates here
        }

        loadContentTypes() {
            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_get_content_types',
                    nonce: aiBotKitMigration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayContentTypes(response.data);
                    } else {
                        this.showError('Failed to load content types: ' + response.data);
                    }
                },
                error: () => {
                    this.showError('Failed to load content types');
                }
            });
        }

        displayContentTypes(contentTypes) {
            const container = $('#content-types-selection');
            let html = '<div class="ai-botkit-content-types-list">';
            
            if (Object.keys(contentTypes).length === 0) {
                html += '<p>No content types available for migration.</p>';
            } else {
                for (const [type, info] of Object.entries(contentTypes)) {
                    html += `
                        <div class="ai-botkit-form-group">
                            <label class="ai-botkit-checkbox-label">
                                <input type="checkbox" name="content_types[]" value="${type}">
                                <span class="ai-botkit-checkbox-text">
                                    <strong>${info.name}</strong>
                                    <span class="ai-botkit-count">(${info.count} items)</span>
                                </span>
                            </label>
                        </div>
                    `;
                }
            }
            
            html += '</div>';
            container.html(html);
        }

        startMigration() {
            // Validate confirmation checkbox
            if (!this.validateCurrentStep()) {
                if (typeof AiBotkitToast !== 'undefined') {
                    AiBotkitToast.error('Please confirm that you have backed up your data');
                } else {
                    alert('Please confirm that you have backed up your data');
                }
                return;
            }
            
            this.showStep('progress');
            
            // Hide all navigation buttons during migration
            $('#ai-botkit-migration-prev').hide();
            $('#ai-botkit-migration-next').hide();
            $('#ai-botkit-migration-start').prop('disabled', true).addClass('disabled');
            $('#migration-log').hide().removeClass('has-content');
            
            // Show progress toast
            let progressToast = null;
            if (typeof AiBotkitToast !== 'undefined') {
                progressToast = AiBotkitToast.loading('Starting migration...', {
                    title: 'Migration in Progress',
                    persistent: true
                });
            }
            
            // Track progress with more realistic simulation
            let progressInterval = null;
            let currentProgress = 0;
            let progressSteps = [
                { progress: 10, message: 'Initializing migration...' },
                { progress: 25, message: 'Preparing data for migration...' },
                { progress: 40, message: 'Processing chunks...' },
                { progress: 60, message: 'Generating embeddings...' },
                { progress: 80, message: 'Storing data...' },
                { progress: 95, message: 'Finalizing migration...' }
            ];
            let currentStep = 0;
            
            const updateProgress = () => {
                if (currentStep < progressSteps.length) {
                    const step = progressSteps[currentStep];
                    currentProgress = step.progress;
                    this.updateProgress(currentProgress, step.message);
                    
                    if (progressToast && typeof AiBotkitToast !== 'undefined') {
                        AiBotkitToast.updateProgress(progressToast, currentProgress);
                    }
                    
                    currentStep++;
                } else {
                    // If we've gone through all steps, just show 95% until completion
                    currentProgress = 95;
                    this.updateProgress(currentProgress, 'Almost complete...');
                    
                    if (progressToast && typeof AiBotkitToast !== 'undefined') {
                        AiBotkitToast.updateProgress(progressToast, currentProgress);
                    }
                }
            };
            
            // Start progress simulation with longer intervals for more realistic feel
            progressInterval = setInterval(updateProgress, 2000);
            
            // Start the migration process with timeout
            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                timeout: 300000, // 5 minutes timeout
                data: {
                    action: 'ai_botkit_start_migration',
                    nonce: aiBotKitMigration.nonce,
                    options: this.migrationOptions
                },
                success: (response) => {
                    // Clear progress interval
                    if (progressInterval) {
                        clearInterval(progressInterval);
                    }
                    
                    // Complete progress
                    this.updateProgress(100, 'Migration completed successfully!');
                    
                    // Hide progress toast
                    if (progressToast && typeof AiBotkitToast !== 'undefined') {
                        AiBotkitToast.hide(progressToast);
                    }
                    
                    // Re-enable start button
                    $('#ai-botkit-migration-start').prop('disabled', false).removeClass('disabled');
                    
                    // Keep Previous button hidden after migration completes
                    $('#ai-botkit-migration-prev').hide();
                    
                    // Show results (progress step stays visible)
                    if (response.success) {
                        this.showMigrationResult(response.data);
                        // Reload migration status to clear "in progress" indicator
                        this.loadMigrationStatus();
                    } else {
                        this.showError('Migration failed: ' + response.data);
                    }
                },
                error: (xhr, status, error) => {
                    // Clear progress interval
                    if (progressInterval) {
                        clearInterval(progressInterval);
                    }
                    
                    // Hide progress toast
                    if (progressToast && typeof AiBotkitToast !== 'undefined') {
                        AiBotkitToast.hide(progressToast);
                    }
                    
                    // Re-enable start button
                    $('#ai-botkit-migration-start').prop('disabled', false).removeClass('disabled');
                    
                    // Keep Previous button hidden after migration error
                    $('#ai-botkit-migration-prev').hide();
                    
                    let errorMessage = 'Migration failed due to a server error';
                    
                    // Provide more specific error messages
                    if (status === 'timeout') {
                        errorMessage = 'Migration timed out. This may be due to a large amount of data. Please try again or contact support.';
                    } else if (status === 'abort') {
                        errorMessage = 'Migration was cancelled.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred during migration. Please check the server logs and try again.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Access denied. Please check your permissions and try again.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network connection lost. Please check your internet connection and try again.';
                    }
                    
                    this.showError(errorMessage);
                }
            });
        }

        updateProgress(percentage, message) {
            $('#migration-progress-fill').css('width', percentage + '%');
            $('#migration-progress-text').text(message);
        }

        showMigrationResult(result) {
            // Keep on progress step - results show below progress bar
            const log = $('#migration-log');
            
            // Determine actual success status based on errors
            const hasErrors = result.error_count && result.error_count > 0;
            const hasMigrated = result.migrated_count && result.migrated_count > 0;
            
            let statusIcon, statusClass, statusText;
            
            if (!hasMigrated && hasErrors) {
                // Complete failure
                statusIcon = '❌';
                statusClass = 'error';
                statusText = 'Failed';
            } else if (hasMigrated && hasErrors) {
                // Partial success
                statusIcon = '⚠️';
                statusClass = 'warning';
                statusText = 'Completed with Errors';
            } else if (hasMigrated && !hasErrors) {
                // Complete success
                statusIcon = '✅';
                statusClass = 'success';
                statusText = 'Completed Successfully';
            } else {
                // No items to migrate
                statusIcon = 'ℹ️';
                statusClass = 'info';
                statusText = 'No Items to Migrate';
            }
            
            // Show the log section when there's content
            log.addClass('has-content').show();
            
            log.html(`
                <div class="ai-botkit-migration-result ${statusClass}">
                    <div class="ai-botkit-result-header">
                        <h5>${statusIcon} Migration ${statusText}</h5>
                    </div>
                    <div class="ai-botkit-result-details">
                        <div class="ai-botkit-result-item">
                            <span class="ai-botkit-result-label">Status:</span>
                            <span class="ai-botkit-result-value ${statusClass}">${statusText}</span>
                        </div>
                        <div class="ai-botkit-result-item">
                            <span class="ai-botkit-result-label">Message:</span>
                            <span class="ai-botkit-result-value">${result.message}</span>
                        </div>
                        ${result.migrated_count ? `
                            <div class="ai-botkit-result-item">
                                <span class="ai-botkit-result-label">Items Migrated:</span>
                                <span class="ai-botkit-result-value success">${result.migrated_count}</span>
                            </div>
                        ` : ''}
                        ${result.error_count ? `
                            <div class="ai-botkit-result-item">
                                <span class="ai-botkit-result-label">Errors:</span>
                                <span class="ai-botkit-result-value error">${result.error_count}</span>
                            </div>
                        ` : ''}
                        ${result.duration ? `
                            <div class="ai-botkit-result-item">
                                <span class="ai-botkit-result-label">Duration:</span>
                                <span class="ai-botkit-result-value">${result.duration}</span>
                            </div>
                        ` : ''}
                        ${result.log_file ? `
                            <div class="ai-botkit-result-item">
                                <span class="ai-botkit-result-label">Log File:</span>
                                <span class="ai-botkit-result-value">
                                    <a href="${aiBotKitMigration.ajaxUrl}?action=ai_botkit_download_migration_log&log_file=${encodeURIComponent(result.log_file)}&nonce=${aiBotKitMigration.nonce}" class="ai-botkit-btn-link" download>
                                        <i class="ti ti-download"></i> Download Log
                                    </a>
                                </span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `);
            
            // Hide start button and show close button
            $('#ai-botkit-migration-start').hide();
            $('#ai-botkit-migration-close').show();
        }

        showError(message) {
            // Keep on progress step - error shows below progress bar
            const log = $('#migration-log');
            // Show the log section when there's content
            log.addClass('has-content').show();
            log.html(`<div class="ai-botkit-error">${message}</div>`);
            
            // Hide start button and show close button
            $('#ai-botkit-migration-start').hide();
            $('#ai-botkit-migration-close').show();
        }

        loadMigrationStatus() {
            // Check if migration section exists (Pinecone configured)
            if ($('.ai-botkit-migration-section').length === 0) {
                return;
            }
            
            // Show loading state and disable interactions
            this.showLoadingState();
            
            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_get_migration_status',
                    nonce: aiBotKitMigration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStatusDisplay(response.data);
                    }
                    this.hideLoadingState();
                },
                error: (xhr) => {
                    $('#local-db-status').text('Error loading status');
                    $('#pinecone-db-status').text('Error loading status');
                    $('#pinecone-connection-status').text('Connection failed');
                    $('#migration-status').text('Error loading status');
                    $('#last-migration').text('Error loading status');
                    
                    // Show specific error message if available
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        $('#pinecone-connection-status').text('Invalid credentials - ' + xhr.responseJSON.data.message);
                        $('#pinecone-connection-status').addClass('ai-botkit-error-text');
                    }
                    
                    this.hideLoadingState();
                }
            });
        }

        updateStatusDisplay(status) {
            // Update local database status
            const localStatus = status.local_database;
            
            // Update Pinecone connection status
            $('#pinecone-connection-status').text('Connected').removeClass('ai-botkit-error-text');
            $('#local-db-status').html(`
                <span class="ai-botkit-status-badge ai-botkit-status-${localStatus.status}">
                    ${localStatus.chunk_count} chunks
                </span>
            `);

            // Update Pinecone database status
            const pineconeStatus = status.pinecone_database;
            let pineconeText = 'Not configured';
            if (pineconeStatus.enabled && pineconeStatus.configured) {
                pineconeText = `${pineconeStatus.chunk_count} chunks`;
            } else if (pineconeStatus.enabled) {
                pineconeText = 'Not configured';
            } else {
                pineconeText = 'Disabled';
            }
            
            $('#pinecone-db-status').html(`
                <span class="ai-botkit-status-badge ai-botkit-status-${pineconeStatus.status}">
                    ${pineconeText}
                </span>
            `);

            // Update migration status
            const migrationInProgress = status.migration_in_progress;
            
            if (migrationInProgress) {
                $('#migration-status').html(`
                    <span class="ai-botkit-status-badge ai-botkit-status-warning">
                        🔄 In Progress
                    </span>
                    <button id="ai-botkit-clear-migration-lock" class="ai-botkit-btn-sm" style="margin-left: 10px; background: #d63638; color: white;">
                        Clear Stuck Migration
                    </button>
                `);
            } else {
                $('#migration-status').html(`
                    <span class="ai-botkit-status-badge ai-botkit-status-success">
                        Ready
                    </span>
                `);
            }

            // Update last migration time
            const lastMigration = status.last_migration;
            const lastMigrationText = lastMigration ? new Date(lastMigration).toLocaleString() : 'Never';
            
            $('#last-migration').html(`
                <span class="ai-botkit-status-badge ai-botkit-status-info">
                    ${lastMigrationText}
                </span>
            `);

            // Update migration button state
            const migrationBtn = $('#ai-botkit-migration-btn');
            if (status.migration_available) {
                migrationBtn.prop('disabled', false).text('Start Migration');
            } else {
                migrationBtn.prop('disabled', true).text('Migration Not Available');
            }
        }

        showLoadingState() {
            // Add loading overlay to migration section
            const migrationSection = $('.ai-botkit-migration-section');
            migrationSection.addClass('ai-botkit-loading');
            
            // Disable all interactive elements
            migrationSection.find('button').prop('disabled', true);
            
            // Show loading spinner on status display
            $('#migration-status-display').addClass('ai-botkit-loading-content');
            
            // Update status text to show loading
            $('#local-db-status').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
            $('#pinecone-db-status').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
            $('#migration-status').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
            $('#last-migration').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
        }

        hideLoadingState() {
            // Remove loading overlay from migration section
            const migrationSection = $('.ai-botkit-migration-section');
            migrationSection.removeClass('ai-botkit-loading');
            
            // Re-enable all interactive elements
            migrationSection.find('button').prop('disabled', false);
            
            // Remove loading spinner from status display
            $('#migration-status-display').removeClass('ai-botkit-loading-content');
        }

        clearMigrationLock() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Clear Stuck Migration?',
                    html: `
                        <div style="text-align: left;">
                            <p><strong>This will clear the migration lock if a migration got stuck.</strong></p>
                            <p>Only use this if:</p>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>A migration failed without completing</li>
                                <li>The system shows "In Progress" but nothing is happening</li>
                                <li>You've waited at least 5 minutes</li>
                            </ul>
                            <p style="color: #d63638; font-weight: bold;">⚠️ Do NOT use this if a migration is actually running!</p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d63638',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Clear Lock',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: aiBotKitMigration.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'ai_botkit_clear_migration_lock',
                                nonce: aiBotKitMigration.nonce
                            },
                            success: (response) => {
                                if (response.success) {
                                    if (typeof AiBotkitToast !== 'undefined') {
                                        AiBotkitToast.success('Migration lock cleared successfully');
                                    }
                                    this.loadMigrationStatus();
                                } else {
                                    if (typeof AiBotkitToast !== 'undefined') {
                                        AiBotkitToast.error(response.data.message || 'Failed to clear migration lock');
                                    }
                                }
                            },
                            error: () => {
                                if (typeof AiBotkitToast !== 'undefined') {
                                    AiBotkitToast.error('Failed to clear migration lock');
                                }
                            }
                        });
                    }
                });
            }
        }

        clearDatabase(database) {
            let databaseName, title, description, actions;
            
            if (database === 'local') {
                databaseName = 'Local Vector Data';
                title = 'Clear Local Vector Data?';
                description = `
                    <div style="text-align: left;">
                        <p><strong>Are you sure you want to clear the LOCAL vector database?</strong></p>
                        <p>This action will clear data stored in your WordPress database:</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Remove all stored vectors and embeddings from local database</li>
                            <li>Delete all chunk data from local storage</li>
                            <li>Clear local migration history</li>
                        </ul>
                        <p style="color: #0073aa; font-weight: bold;">ℹ️ Pinecone data will NOT be affected</p>
                        <p style="color: #dba617; font-weight: bold;">📋 Document metadata will be preserved for knowledge base display</p>
                        <p style="color: #d63638; font-weight: bold;">⚠️ This action cannot be undone!</p>
                    </div>
                `;
                actions = ['Remove all local vector data', 'Clear local migration history'];
            } else if (database === 'pinecone') {
                databaseName = 'Pinecone Database';
                title = 'Clear Pinecone Database?';
                description = `
                    <div style="text-align: left;">
                        <p><strong>Are you sure you want to clear the Pinecone Database?</strong></p>
                        <p>This action will:</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Remove all vectors from Pinecone</li>
                            <li>Delete all embeddings stored in Pinecone</li>
                            <li>Clear Pinecone index data</li>
                        </ul>
                        <p style="color: #d63638; font-weight: bold;">⚠️ This action cannot be undone!</p>
                    </div>
                `;
                actions = ['Remove all Pinecone vectors', 'Clear Pinecone index'];
            } else if (database === 'knowledge_base') {
                databaseName = 'Knowledge Base';
                title = 'Clear Entire Knowledge Base?';
                description = `
                    <div style="text-align: left;">
                        <p><strong>Are you sure you want to clear the ENTIRE Knowledge Base?</strong></p>
                        <p>This action will:</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Remove all documents and their metadata</li>
                            <li>Delete all chunks and embeddings</li>
                            <li>Clear all chatbot associations</li>
                            <li>Remove all content relationships</li>
                        </ul>
                        <p style="color: #d63638; font-weight: bold;">🚨 This will completely empty your knowledge base!</p>
                        <p style="color: #d63638; font-weight: bold;">⚠️ This action cannot be undone!</p>
                    </div>
                `;
                actions = ['Remove all documents', 'Clear all associations', 'Delete entire knowledge base'];
            }
            
            // Use SweetAlert2 for confirmation
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    html: description,
                    icon: database === 'knowledge_base' ? 'error' : 'warning',
                    showCancelButton: true,
                    confirmButtonColor: database === 'knowledge_base' ? '#d63638' : '#dba617',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: `Yes, Clear ${databaseName}`,
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    focusCancel: true,
                    customClass: {
                        popup: 'ai-botkit-swal-popup',
                        confirmButton: database === 'knowledge_base' ? 'ai-botkit-swal-confirm-danger' : 'ai-botkit-swal-confirm-warning',
                        cancelButton: 'ai-botkit-swal-cancel'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.performDatabaseClear(database, databaseName);
                    }
                });
            } else {
                // Fallback to native confirm
                if (confirm(`Are you sure you want to clear the ${databaseName}? This action cannot be undone.`)) {
                    this.performDatabaseClear(database, databaseName);
                }
            }
        }

        performDatabaseClear(database, databaseName) {
            // Show loading state
            this.showLoadingState();

            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_clear_database',
                    database: database,
                    nonce: aiBotKitMigration.nonce
                },
                success: (response) => {
                    this.hideLoadingState();
                    if (response.success) {
                        // Success notification with SweetAlert2
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Database Cleared!',
                                text: `Successfully cleared ${databaseName}`,
                                icon: 'success',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                customClass: {
                                    popup: 'ai-botkit-swal-popup'
                                }
                            });
                        } else {
                            alert(`Successfully cleared ${databaseName}`);
                        }
                        this.loadMigrationStatus(); // Refresh status
                    } else {
                        // Error notification with SweetAlert2
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Clear Failed',
                                html: `
                                    <p>Failed to clear ${databaseName}</p>
                                    <p style="color: #d63638; font-size: 14px; margin-top: 10px;">
                                        <strong>Error:</strong> ${response.data.message || 'Unknown error'}
                                    </p>
                                `,
                                icon: 'error',
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'ai-botkit-swal-popup'
                                }
                            });
                        } else {
                            alert(`Failed to clear ${databaseName}: ${response.data.message || 'Unknown error'}`);
                        }
                    }
                },
                error: () => {
                    this.hideLoadingState();
                    // Network error notification with SweetAlert2
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Network Error',
                            text: `Failed to clear ${databaseName}. Please check your connection and try again.`,
                            icon: 'error',
                            confirmButtonText: 'OK',
                            customClass: {
                                popup: 'ai-botkit-swal-popup'
                            }
                        });
                    } else {
                        alert(`Failed to clear ${databaseName}`);
                    }
                }
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new MigrationWizard();
    });

})(jQuery);
